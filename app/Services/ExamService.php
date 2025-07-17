<?php

namespace App\Services;
use App\Models\Exam;
use Illuminate\Support\Collection;
class ExamService
{
    public function getCourseForExam(Exam $exam)
    {
        return $exam->course()->with('subjects.topics.questions')->first();
    }

    public function getQuestionsForExam(Exam $exam)
    {
        return $exam->questions()->with('topic.subject')->get();
    }

    public function getAvailableQuestionsForExam(Exam $exam)
    {
        $exam_course = $this->getCourseForExam($exam); 

        $exam_questions = $exam->questions()->pluck('question_id');

        $available_questions = $exam_course->subjects->flatMap(function ($subject) {
            return $subject->topics->flatMap->questions;
        });

        return $available_questions->whereNotIn('id', $exam_questions);
    }

    public function getTopicsForExam(Exam $exam){
        $exam_questions = $this->getQuestionsForExam($exam);
        $exam_topics = $exam_questions
            ->groupBy(fn($question) => $question->topic->id)
            ->map(function($questions) {
                $topic = $questions->first()->topic;
                $topic->question_count = $questions->count();
                return $topic;
            })
            ->sortBy([
                ['question_count', 'desc'],
                ['name', 'asc'],
            ]);

        return $exam_topics;
    }

    public function getSubjectsForExam(Exam $exam){
        $exam_topics = $this->getTopicsForExam($exam);
        $exam_subjects = $exam_topics            
            ->groupBy(fn($topic) => $topic->subject->id)
            ->map(function($topics) {
                $subject = $topics->first()->subject;
                $subject->question_count = $topics->sum('question_count');
                return $subject;
            })
            ->sortBy([
                ['question_count', 'desc'],
                ['name', 'asc'],
            ]);
        return $exam_subjects;
    }
    public function getQuestionTypeCounts(Exam $exam): array
    {
        $questions = $this->getQuestionsForExam($exam); // already eager loaded

        return $questions->groupBy('question_type')->map(function ($type) {
            return $type->count();
        })->toArray();
    }


    public function transformQuestionRows(Collection $questions)
    {
        return $questions->map(fn ($question) => [
            'id' => $question->id, 
            'name' => $question->name,
            'subject' => $question->topic->subject->name,
            'topic' => $question->topic->name,
            'type' => $question->question_type->name
        ]);
    }




    // algorithm for fetching and building the exam
    public function assignValuesToQuestionsForKnapsack(Exam $exam, $subject_weight, $criteria)
    {
        // Get all questions related to the exam’s course
        $course = $this->getCourseForExam($exam);
        $knapsack = [];

        // Check if course does exist   
        if ($course) {
            $topic_weight = 1 - $subject_weight;

            $questions = $course->subjects->flatMap(function ($subject) {
                                            return $subject->topics->flatMap->questions;
                                        });

            // Count questions per subject and topic
            $questions_in_subjects = $questions->groupBy(fn($question) => $question->topic->subject->id)
                                            ->map->count();
            $questions_in_topics = $questions->groupBy(fn($question) => $question->topic->id)
                                            ->map->count();

            // Assign score to each question
            $scored_questions = $questions->map(function ($question) use ($questions_in_subjects, $questions_in_topics, $subject_weight, $topic_weight, $criteria) {
                // get subject and topic ids
                $subject_id = $question->topic->subject->id;
                $topic_id = $question->topic->id;

                // get total counts of subjects and topics
                $total_questions_in_subject = $questions_in_subjects[$subject_id] ?? 1;
                $total_questions_in_topic = $questions_in_topics[$topic_id] ?? 1;

                // assign scores for coverage in subject and topic
                // The subject and topic score is using inverse proportional scoring scheme
                // which means higher count leads to lower score so that lower counts can still be picked
                $question->topic_coverage_score = 1 / $total_questions_in_topic;
                $question->subject_coverage_score = 1 / $total_questions_in_subject;

                // Calculate value
                    // value = ax + by
                // Wherein:
                    // a and b = subject weight and topic weight
                    // x and y = subject score and topic score
                    // subject score = 1/ total questions in subject
                    // topic score = 1/ total questions in topic
                $question->coverage_score = $subject_weight * $question->subject_coverage_score + $topic_weight * $question->topic_coverage_score;

                // calculate value density = value/weight 
                // Wherein: 
                    // value = coverage score 
                    // weight = question points
                // The 'best' is defined by criteria sent by user
                $question->value = $criteria === 'value'
                    ? $question->coverage_score + 1
                    : ($question->coverage_score + 1) / ($question->points ?: 1);
                return $question;
            });
            // prepare data to represent set of questions to pick as Knapsack
            // Normalize the value so that values are within the range of 0 - 1.
            // Normalization = (value - min(value) ) / (max(value) - min(value))
            $min = $scored_questions->min('value');
            $max = $scored_questions->max('value');
            $normalized_questions = $scored_questions->map(function ($question) use ($min, $max) {
                $question->value = ($question->value - $min) / max($max - $min, 1e-8);
                return $question;
            });

            $knapsack = $normalized_questions->map(fn($question) => ['id'=>$question->id, 'name'=>$question->name, 'value'=>$question->value, 'weight'=>$question->points]);
        }
        \Log::debug('Knapsack prepared:', $knapsack->toArray());
        return $knapsack;
    }

    public function useGreedyAlgorithm(Exam $exam, $subject_weight, $criteria){
        $valued_questions = $this->assignValuesToQuestionsForKnapsack($exam, $subject_weight, $criteria);
        // We sort this to start being greedy by value
        $item_questions = $valued_questions->sortByDesc('value');
        $question_combination = []; 
        $max_weight = $exam->max_score;
        $total_value = 0.0;
        $total_weight = 0.0;

        // Since the questions are sorted we can just fetch them from left to right
        foreach ($item_questions as $item) {
            if (($total_weight + $item['weight']) <= $max_weight) {
                $question_combination[] = $item;
                $total_weight += $item['weight'];
                $total_value += $item['value'];
            }
        }

        $data = [
            'questions' => $question_combination, 
            'total value' => round($total_value * 1000), 
            'Exam Max Score' => $max_weight, 
            'total weights' => $total_weight,
            'algorithm' => 'Greedy Algorithm',
        ];
        // dump($data);
        return $data;
    }

    public function useDynamicProgramming(Exam $exam, $subject_weight, $criteria){
        $questions = $this->assignValuesToQuestionsForKnapsack($exam, $subject_weight, $criteria);
        $rounded_valued_questions = $questions->map(function ($question) {
            $question['value'] = (int) round($question['value'] * 1000);
            return $question;
            });
        $question_combination = [];
        $max_weight = $exam->max_score;
        $total_value = 0.0;

        // We add one to rows and columns because of zero-indexed array nature
        $rows = $rounded_valued_questions->count() + 1;
        $columns = $max_weight + 1;
        $dynamic_programming_table =  array_fill(0, $rows, array_fill(0, $columns, 0));

        // No items or zero capacity, so no value can be obtained
        if ($rounded_valued_questions->count() == 0 || $max_weight == 0) {
            return 0;  
        }

        // No need to sort because it will compute for all values anyways
        for ($item = 1; $item < $rows; $item++) {
            // Since the questions are object we need to save each objects (rows) weights and values
            // The reason for $item - 1 is because of zero-based index array 
            $item_weight = $rounded_valued_questions[$item - 1]['weight'];
            $item_value = $rounded_valued_questions[$item - 1]['value'];

            // These are the columns of the dp table
            for ($weight = 1; $weight < $columns; $weight++){

                // We check if the item (question) weight is greater than the column number because columns are represented as weights
                if ($item_weight <= $weight){
                    // This compare the value when the item (question) is excluded vs not excluded and take the highest value between the two
                    $dynamic_programming_table[$item][$weight] = max(
                        $dynamic_programming_table[$item - 1][$weight],
                        $dynamic_programming_table[$item - 1][$weight - $item_weight] + $item_value
                    );
                } else {
                    // Since the $item_weight is over it is automatically skipped
                    $dynamic_programming_table[$item][$weight] = $dynamic_programming_table[$item - 1][$weight];
                }

            }
          }

          // This code will start fetching the optimal set of questions
          $weight_remaining = $max_weight;
          // We start from the leftmost cell of the table
          for ($item = $rows - 1; $item > 0; $item--) {
            // Check if the item (question) is not the same as the cell above it; if it is the same it means we don't take the item (question)
            if ($dynamic_programming_table[$item][$weight_remaining] != $dynamic_programming_table[$item - 1][$weight_remaining]) {
                  $question_item =  $rounded_valued_questions[$item - 1];
                  $question_combination[] = $question_item;  // fetch the selected question
                  $weight_remaining -= $question_item['weight'];  
                  $total_value += $question_item['value']; 
              }
          }

        $data = [
            'questions' => $question_combination,
            'total value' => $total_value, 
            'Exam Max Score' => $max_weight, 
            'weight remaining' => $weight_remaining,
            'algorithm' => 'Dynamic Programming'
        ];
                // dump($data);

        return $data;
    }

    // algorithm for shuffling the question list
}

