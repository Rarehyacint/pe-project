<?php

namespace App\Services;
use App\Jobs\SubmitExpiredPaper;
use App\Models\Exam;
use App\Models\ExamRecord;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\StudentPaper;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Events\ExamSubmitted;
use DB;
use Storage;
use Str;
class ExamTakingService
{
    protected $questionService;

    public function __construct(QuestionService $questionService)
    {
        $this->questionService = $questionService;
    }
    public function validateExamAccess(Exam $exam, User $user){
        $isEnrolled = $user->exams()->where('exam_id', $exam->id)->exists();

        if (!$isEnrolled){
            return false;
        }

        if (!$exam->is_published){
            return false;
        }
                
        $now = now();
        if ($exam->examination_date && $exam->expiration_date) {
            if ($now >= $exam->examination_date && $now <= $exam->expiration_date) {
                // return false;
                return true; // dapat pwede ka magtake ng exam
            }
        }



        $student_attempts_left = $this->getAttemptsLeft($exam, $user);
        if($student_attempts_left == 0){
            return false;
        }
            
        return true;
    }

    public function getAttemptsLeft(Exam $exam, User $user){
        $get_student_paper_count = $user->exams()->where('exam_id', $exam->id)->withCount('studentPapers')->first()->student_papers_count;
        if ($exam->retakes == null){
            $attempt_left = 99;
        } else {
            $attempt_left = max(0, $exam->retakes - $get_student_paper_count);
        }
        return $attempt_left;
    }

    public function checkUnsubmittedExamPaper(Exam $exam, User $user){
        $exam_paper = $user->studentPapers()->where(['exam_id' => $exam->id, 'status' => 'in_progress'])->first() ?? false;
        if (!$exam_paper){
            $exam_paper = $this->generateExamPaper($exam, $user);
        } else {
            $exam_paper['last_seen_at'] = now();
        }
        return $exam_paper;

    }
    public function checkBooleanUnsubmittedExamPaper(Exam $exam, User $user){
        $exam_paper = $user->studentPapers()->where(['exam_id' => $exam->id, 'status' => 'in_progress'])->first() ?? false;
        if (!$exam_paper){
            return false;
        } else {
            return true;
        }
    }

    public function generateExamPaper(Exam $exam, User $user){
        $exam->load('questions');
        $exam_duration = $exam->duration ?? null;
        // Prepare student paper information
        $shuffled_ids = self::applyKnuthShuffleToExam($exam);
        $question_count = count($shuffled_ids);

        // Create student paper
        $student_paper = StudentPaper::create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'questions_order' => json_encode($shuffled_ids),
            'question_count' => $question_count,
            'current_position' => 0,
            'expired_at' => $exam_duration != null ? now()->addMinutes($exam_duration) : null
        ]);


        foreach($shuffled_ids as $id){
            StudentAnswer::create([
                'student_paper_id' => $student_paper->id,
                'question_id' => $id
            ]);
        }

        // Put data to be shown to user
        $questions = self::getShuffledQuestionsInfo($exam, $shuffled_ids);

        $exam->unsetRelation('questions');
        $student_paper->questions_in_array = $questions;

        if($student_paper->expired_at != null){
            SubmitExpiredPaper::dispatch($student_paper, $this, $user)->delay(now()->addMinutes($exam->duration));
        }

        return $student_paper;
    }

    // algorithm for shuffling the question list
    public static function applyKnuthShuffleToExam(Exam $exam){
        $questions = $exam->questions->pluck('id')->toArray();

        // knuth shuffle
        for ($i = count($questions) - 1; $i > 0; $i--) {
            $picked_item = random_int(0, $i);
            [$questions[$i], $questions[$picked_item]] = [$questions[$picked_item], $questions[$i]];
        }
        return $questions;
    }

    public static function getShuffledQuestionsInfo(Exam $exam, array $shuffled_ids){
        $questions = $exam->questions->whereIn('id', $shuffled_ids)->keyBy('id');
        // $questions = collect($shuffled_ids)->map(fn($id) => [
        //     'id' => $questions[$id]->id,
        //     'question_type' => $questions[$id]->question_type->value
        // ])->pluck('question_type' ,'id')->toArray();
        return $questions;
    }

    public function getCurrentQuestion(StudentPaper $student_paper){
        if ($student_paper->current_position >= $student_paper->question_count){
                $student_paper->update(['current_position' => 0]);
        }
        $questions = json_decode($student_paper->questions_order);
        $question = Question::find($questions[$student_paper->current_position]);

        // check if there are answers regarding this question and send it instead of new question
        $student_answer = $student_paper->studentAnswers()->where('question_id', $question->id)->first();
        $question_type_data = $this->questionService->getQuestionTypeShow($question);
        
        $question_type_data = self::filterQuestionTypeData($question, $question_type_data);
        
        $filtered_question_type['choices'] = $question_type_data; 
        if($student_answer->is_answered){
            $question_type_answer = $this->getAnswerType($student_answer, $question);
            if ($question_type_answer != null){
                
                if ($question->question_type->value == 'coding'){
                    $filtered_question_type['choices']['language_codes'][$question_type_answer['language']]['initial_solution'] = $question_type_answer['code'];
                } else {
                    $filtered_question_type['student_answer'] = $question_type_answer;
                }

            } 
        }


        $question_data = [
            'question' => $question,
            'question_type_data' => $filtered_question_type
        ];
        
        if($student_answer->first_viewed_at == null){
            $student_answer->update(['first_viewed_at' => now()]);
        }

        return $question_data;
    }

    public static function filterQuestionTypeData(Question $question, $question_type){
        switch ($question->question_type->value){
            case('multiple_choice') :
                unset($question_type['points']);
                foreach ($question_type as $item => &$data) {
                    unset($data['is_solution']);
                }                
                break;
            case('true_or_false') :
                unset($question_type['solution']);
                unset($question_type['points']);
                break;
            case('identification') :
                unset($question_type['solution']);
                unset($question_type['points']);
                break;
            case('ranking') :
                foreach ($question_type as $item => &$data) {
                    unset($data['order']);
                    unset($data['item_points']);
                }
                shuffle($question_type);
                break;
            case('matching') :
                foreach ($question_type as $item => &$data) {
                    unset($data['item_points']);
                }
                $rightItems = array_column($question_type, 'right');
                shuffle($rightItems); 

                foreach ($question_type as $index => &$pair) {
                    $pair['right'] = $rightItems[$index];
                }
                
                shuffle($question_type);
                break;
            case('coding') : 
                foreach ($question_type['language_codes'] as $item => &$data) {
                    unset($data['complete_solution']);
                }
                $question_type['languages'] = $question_type['languages']->mapWithKeys(function ($item) {
                    return [$item => $item];
                });
                unset($question_type['syntax_points']);
                unset($question_type['runtime_points']);
                unset($question_type['test_case_points']);
                break;
        }

        return $question_type;
    }

    public function getAnswerType(StudentAnswer $student_answer, Question $question){
        match ($question->question_type->value){
            'multiple_choice' => $student_answer->load('multipleChoiceAnswer'),
            'true_or_false' => $student_answer->load('trueOrFalseAnswer'),
            'identification' => $student_answer->load('identificationAnswer'),
            'ranking' => $student_answer->load('rankingAnswers'),
            'matching' => $student_answer->load('matchingAnswers'),
            'coding' => $student_answer->load('codingAnswer'),
            default => throw new \InvalidArgumentException("Unknown question type: {$question->question_type->value}"),
        };

        if ($question->question_type->value == 'coding'){
            $coding_answer = $student_answer->codingAnswer ?? false;

            if (!$coding_answer) {
                return null;
            }

            $language = $coding_answer->answer_language;
            $code = Storage::get($coding_answer->answer_file_path);
            
            return ['language' => $language, 'code' => $code];
        } else {
            $suffix = in_array($question->question_type->value, ['ranking', 'matching']) ? 'Answers' : 'Answer';
            $question_type_answer = Str::camel($question->question_type->value) . $suffix;

            return $student_answer->$question_type_answer;
        }
    }

    public function orderedQuestions(StudentPaper $student_paper, Exam $exam)
    {
        $order = json_decode($student_paper->questions_order, true);
        $questionsMap = $exam->questions->whereIn('id', $order)->keyBy('id');

        return collect($order)->map(function ($id, $index) use ($questionsMap) {
            $question = $questionsMap->get($id);
            if ($question) {
                $question->order_index = $index;
                return $question;
            }
            return null;
        })->filter()->values();
    }

    public static function submitPaperIfExpired(StudentPaper $student_paper){
        if ($student_paper->isSubmitted()){
            return false;
        }

        if ($student_paper->isExpired()){
            $student_paper->status = "auto_completed";
            return true;
        }

        return false;
    }

    public function submitPaper(StudentPaper $student_paper, User $user){
        // validate that the student_paper's author is the authenticated user
        $student_paper->update(['submitted_at' => now()]);

        $exam_id = $student_paper->exam->id;
        $exam = Exam::find($exam_id);

        $attempt_count = ExamRecord::whereHas('studentPaper', function($query) use ($exam_id, $student_paper) {
            $query->where('exam_id', $exam_id)
                ->where('user_id', $student_paper->user_id);
        })->count();
        
        // AGGREGATE student points by subjects, get subject id, subject name, sum of student points for that subject, sum of obtainable points for subject
        $subject_table = DB::table('student_answers')
            ->join('questions', 'student_answers.question_id', '=', 'questions.id')
            ->join('topics', 'questions.topic_id', '=', 'topics.id')
            ->join('subjects', 'topics.subject_id', '=', 'subjects.id')
            ->where('student_answers.student_paper_id', $student_paper->id)
            ->groupBy('subjects.id', 'subjects.name')
            ->select(
                'subjects.id as id',    
                'subjects.name as subject_name',
                DB::raw('SUM(student_answers.points) as subject_score_obtained'),
                DB::raw('SUM(questions.total_points) as subject_score'))
            ->get()
            ->keyBy('id');

        $total_score = $subject_table->sum('subject_score_obtained');
        $date_taken = $student_paper->created_at;
        
        $time_taken = round($student_paper->created_at->diffInMinutes($student_paper->submitted_at));

        $exam_record = $student_paper->examRecord()->updateOrCreate(
        ['student_paper_id' => $student_paper->id],
            [
                    'attempt' => $attempt_count + 1,
                    'total_score' => $total_score,
                    'date_taken' => $date_taken,
                    'time_taken' => $time_taken,
            ]);

        $transformed_subject_table = $subject_table->map(function ($item) use ($exam_record) {
            return [
                'exam_record_id'   => $exam_record->id,
                'subject_id'       => $item->id,
                'subject_name'     => $item->subject_name,
                'score_obtained'   => (int) $item->subject_score_obtained,
                'score'            => (int) $item->subject_score,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        })->values()->toArray();

        DB::table('exam_records_subjects')->upsert(
        $transformed_subject_table,
        ['exam_record_id', 'subject_id'],
        ['score_obtained', 'score', 'updated_at']
        );
        
        $coding_question_answers_pattern = "user:{$user->id}:paper:{$student_paper->id}:language:*:answer:*:code";

        $keys = Redis::keys($coding_question_answers_pattern);

        if (!empty($keys)) {
            Self::storeCodeToJSON($user->id, $student_paper->id);
        } else {
            $score = $exam_record->total_score;

            if ($score == $exam->max_score) {
                $status = 'perfect_score';
            } elseif ($score >= $exam->max_score * ($exam->passing_score / 100)) {
                $status = 'pass';
            } else {
                $status = 'more_review';
            }

            $exam_record->update(['status' => $status]);
            
            if($student_paper->status != "auto_completed"){
                $student_paper->update(['status'  => 'completed']);
            }
        }

        // Dispatch ExamSubmitted after commit so listeners see consistent DB state
        DB::afterCommit(function () use ($user, $exam) {
            event(new ExamSubmitted($user, $exam));
        });

        return [
            'exam' => $exam,
            'exam_record' => $exam_record,
        ];
    }

    private static function storeCodeToJSON($user_id, $student_paper_id){
        $pattern = "user:$user_id:paper:$student_paper_id:language:*:answer:*:code";
        // $student_paper_date = StudentPaper::find($student_paper_id)->submitted_at;
        // $student_paper_submitted_at_unix = (String) Carbon::parse($student_paper_date)->timestamp;
        // $key = $student_paper_submitted_at_unix . '-' . $user_id;

        $keys = Redis::keys($pattern); 
        // $values = Redis::mget($keys);
        $data = [];

        foreach ($keys as $index => $key) {
            try {
                $hashData = Redis::hgetall($key);

                preg_match('/paper:([^:]+)/', $key, $student_paper_matches);
                $student_paper_id = (int)$student_paper_matches[1] ?? null;

                preg_match('/language:([^:]+)/', $key, $language_matches);
                $language = $language_matches[1] ?? null;

                preg_match('/answer:(\d+)/', $key, $answer_matches);
                $answer_id = isset($answer_matches[1]) ? (int)$answer_matches[1] : null;

                preg_match('/coding_answer:(\d+)/', $key, $coding_answer_matches);
                $coding_answer_id = isset($coding_answer_matches[1]) ? (int)$coding_answer_matches[1] : null;


                $data[] = [
                    'student_paper_id' => $student_paper_id,
                    'answer_id' => $answer_id,
                    'coding_answer_id'     => $coding_answer_id,
                    'language'      => $language,
                    'data'          => $hashData,
                ];
                
                Redis::del($key);
            } catch (\Exception $e) {
                // log the error
                continue;
            }
        }

        $json_pretty_print = json_encode($data, JSON_PRETTY_PRINT);
        $json = json_encode($data);

        $folder = "codeInJSON/";

        $answer_file_path = "{$folder}user_{$user_id}:paper_{$student_paper_id}.json";

        Storage::makeDirectory($folder);
        Storage::put($answer_file_path, $json_pretty_print);
        
        Redis::XADD("code_checker", '*', ["data" => $json]);

    }

    public function getExamRecordFromStudentPaper(StudentPaper $student_paper){
        $exam_record = ExamRecord::where('student_paper_id', "=", $student_paper->id)->get();
        if ($exam_record ){
            $exam = $student_paper->exam;

            $exam_result = [
                'exam' => $exam,
                'exam_record' => $exam_record
            ];
            return $exam_result;
        } else {
            return false;
        }
    }

}

