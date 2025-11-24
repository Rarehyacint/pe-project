<?php

namespace App\Services;
use App\Models\Course;
use App\Models\Exam;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Question;
use Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Storage;
use Str;
class AnswerService
{
    protected $result = [];
    protected $total_points = 0;
    protected $is_correct = false;
    public function storeMultipleChoice(StudentAnswer $student_answer, $answer, $question_type){
        foreach($question_type['choices'] as $choice){
            if ($choice['choice_key'] === $answer) {
                if ($choice['is_solution']){
                    $this->total_points += $question_type['points'];
                    $this->is_correct = true;
                }
            }
        }
        $student_answer->multipleChoiceAnswer()->updateOrCreate(
             ['student_answer_id' => $student_answer->id],
             ['answer' => $answer]);

        return ['total_points' => $this->total_points, 'is_correct' => $this->is_correct];
    }
    public function storeTrueOrFalse(StudentAnswer $student_answer, $answer, $question_type, $question){
        if($answer === $question_type['solution']){
            $this->total_points = $question['total_points'];
            $this ->is_correct = true;
        }         
        $student_answer->trueOrFalseAnswer()->updateOrCreate(
             ['student_answer_id' => $student_answer->id],
             ['answer' => $answer]);
        
        return ['total_points' => $this->total_points, 'is_correct' => $this->is_correct];
    }
    public function storeIdentification(StudentAnswer $student_answer, $answer, $question_type, $question){
        if($answer === $question_type['solution']){
            $this->total_points = $question['total_points'];
            $this ->is_correct = true;
        } 
    
        $student_answer->identificationAnswer()->updateOrCreate(
             ['student_answer_id' => $student_answer->id],
             ['answer' => $answer]);
        return ['total_points' => $this->total_points, 'is_correct' => $this->is_correct];
    }
    public function storeRanking(StudentAnswer $student_answer, $answer, $question_type, $question){
        $current_total_points = 0;
        foreach ($answer as $index => $row_answer) {
            $matched = false;

            foreach ($question_type as $correct) {
                if ($row_answer === $correct['solution'] && $index+1 === $correct['order']) {
                    $results[$index] = [
                        'answer_order' => $correct['order'],
                        'answer' => $row_answer,
                        'item_points' => $correct['item_points'],
                    ];
                    $this->total_points += $correct['item_points'];
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $results[$index] = [
                    'answer_order' => $index + 1, // fallback if order unknown
                    'answer' => $row_answer,
                    'item_points' => 0,
                ];
            }
        }
        $student_answer->rankingAnswers()->delete();
        foreach($results as $result => $data){
            $student_answer->rankingAnswers()->Create(
                 [
                'answer_order' => $data['answer_order'],
                'answer' => $data['answer'],
                'answer_points' => $data['item_points']
            ]);
            $current_total_points += $data['item_points'];
        }
        $this->is_correct = $question['total_points'] === $current_total_points;

        
        return ['total_points' => $this->total_points, 'is_correct' => $this->is_correct];
    }
    public function storeMatching(StudentAnswer $student_answer, $answer, $question_type, $question){
        $current_total_points = 0;
        foreach ($answer as $index => $student) {
            $matched = false;
            foreach ($question_type as $correct) {
                if (
                    $student['left'] === $correct['left'] &&
                    $student['right'] === $correct['right']
                ) {
                    $results[$index] = [
                        'left' => $student['left'],
                        'right' => $student['right'],
                        'item_points' => $correct['item_points'],
                    ];
                    $this->total_points += $correct['item_points'];
                    $matched = true;
                    break;
                }
            }
            
            if (!$matched) {
                $results[$index] = [
                    'left' => $student['left'],
                    'right' => $student['right'],
                    'item_points' => 0,
                ];
            }
        }
        $student_answer->matchingAnswers()->delete();
        foreach($results as $result => $data){
            $student_answer->matchingAnswers()->Create(
                [
                'first_item_answer' => $data['left'],
                'second_item_answer' => $data['right'],
                'answer_points' => $data['item_points']
                ]);
            $current_total_points += $data['item_points'];
        }
        $this->is_correct = $question['total_points'] === $current_total_points;
        
        return ['total_points' => $this->total_points, 'is_correct' => $this->is_correct];
    }
    public function storeCoding(StudentAnswer $student_answer, $answer, $question, $attempt_count){
        $language = $answer['programming_language'];

        $user = auth()->user();
        $exam = $student_answer->studentPaper->exam;

        $student_slug_name = Str::slug($user->first_name . ' ' . $user->last_name);
        $question_slug_name = Str::slug($question['name']);
        $folder = "codingAnswers/{$student_slug_name}/exam_{$exam->id}/{$question_slug_name}/";
        $attempt_count_folder = "attempt_{$attempt_count}/";
        $ext = self::getExtension($language);
        $coding_answer = self::getClassName($answer['code'], $language);
        $answer_file_path = "{$folder}{$attempt_count_folder}{$coding_answer}.{$ext}";

        Storage::makeDirectory($folder);
        Storage::put($answer_file_path, $answer['code']);
        
        $coding_answer = $student_answer->codingAnswer()->updateOrCreate([
            'answer_language' => $language,
            'answer_file_path' => $answer_file_path
        ]);
        
        $key = "user:$user->id:paper:$student_answer->student_paper_id:language:$language:answer:$student_answer->id:coding_answer:$coding_answer->id:code";
        $question = Question::find($student_answer->question_id);
        $question_type = $question->getTypeModel();
        $test_case = $question_type->getSpecificLanguage($language);

        $data = [
            'code' => $answer['code'],
            'testUnit' => $test_case->getTestCase(),
            'syntax_points' => $question_type->syntax_points,
            'runtime_points' => $question_type->runtime_points,
            'test_case_points' => $question_type->test_case_points,
            'syntax_points_deduction' => $question_type->syntax_points_deduction_per_error,
            'runtime_points_deduction' => $question_type->runtime_points_deduction_per_error,
            'test_case_points_deduction' => $question_type->test_case_points_deduction_per_error,
        ];
        
        Redis::hmset($key, $data);

        return ['total_points' => $this->total_points, 'is_correct' => $this->is_correct];
    }

    private static function getClassName(string $code, string $language): string
    {
        $patterns = [
            'java' => '/\bclass\s+(\w+)/',
            'c++' => '/\b(?:class|struct)\s+(\w+)/',
            'python' => '/^[ \t]*class\s+(\w+)/m',
        ];

        $pattern = $patterns[strtolower($language)] ?? '/\bclass\s+(\w+)/';
        $class_name = 'noClassName';

        if (preg_match($pattern, $code, $matches)) {
            $class_name = $matches[1];
        }

        return $class_name;
    }

    private static function getExtension(string $language): string
    {
        return match (strtolower($language)) {
            'java' => 'java',
            'python' => 'py',
            'c++' => 'cpp',
            default => 'txt',
        };
    }

    public function hasAnswerChanged($student_answer, $answer) {
        $key = "paper:$student_answer->student_paper_id:student_answer_id:$student_answer->id";

        $hash = md5(json_encode($answer));

        $oldHash = Redis::get($key);

        if (!$oldHash || $hash !== $oldHash) {
            if ($student_answer['is_answered']){
                $student_answer->update(['last_answered_at' => now()]);
            };

            Redis::setex($key, 43200, $hash);
            
            return true; 
        }
        return false;
    }

}
