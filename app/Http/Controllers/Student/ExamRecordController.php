<?php

namespace App\Http\Controllers\Student;

use App\Models\CodingAnswer;
use App\Models\Exam;
use App\Models\ExamRecord;
use App\Models\Reviewer;
use App\Models\StudentAnswer;
use App\Models\StudentPaper;
use App\Services\ExamTakingService;
use App\Services\QuestionService;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use Storage;
use Str;

class ExamRecordController extends Controller
{
    protected $examTakingService;

    public function __construct(ExamTakingService $examTakingService)
    {
        $this->examTakingService = $examTakingService;
    }
    public function index(Exam $exam)
    {
        $user = auth()->user();
        $exam_records = ExamRecord::whereHas('studentPaper', function ($query) use ($exam, $user) {
            $query->where('exam_id', $exam->id)
                ->where('user_id', $user->id);
        })->with('subjects')->get();
    
        
        return view( 'students/records/index', ['exam' => $exam, 'exam_records' => $exam_records]);
    }

    public function store(StudentPaper $student_paper)
    {
        $user = auth()->user();
        $exam_result = $this->examTakingService->submitPaper($student_paper, $user);
        return response('', 204)->header('HX-Redirect', route('exam_records.show', ['exam' => $exam_result['exam'], 'exam_record' => $exam_result['exam_record']]));
    }

    public function show(Exam $exam, ExamRecord $examRecord)
    {
        $examRecord->load('subjects');
        $student_paper = StudentPaper::with('studentAnswers.question')
            ->find($examRecord->student_paper_id);

        $rows = [];
        foreach ($student_paper->studentAnswers as $i => $answer) {
            $question = $answer->question;
            $question->load('multipleChoiceQuestions');

            $neededRelations = $student_paper->studentAnswers
                ->map(fn($a) => $a->question->question_type->value)
                ->unique()
                ->map(fn($type) => 'studentAnswers.' . Str::camel($type) . (in_array($type, ['ranking', 'matching']) ? 'Answers' : 'Answer'))
                ->values()
                ->all();

            $student_paper->load(array_merge(['studentAnswers.question'], $neededRelations));

            // Load and get the specific answer
            $question_service = new QuestionService();
            $exam_taking_service = new ExamTakingService($question_service);

            if ($answer->is_answered){
                $question_type_answer = $exam_taking_service->getAnswerType($answer, $question);
                if ($question_type_answer != null){
                    switch ($question->question_type->value) {
                        case 'multiple_choice':
                            $matched = $question->multipleChoiceQuestions->firstWhere('choice_key', $question_type_answer?->answer);
                            $yourAnswer = $matched->item ?? 'N/A';
                            break;
                        case 'identification':
                            $yourAnswer = $question_type_answer?->answer ?? 'N/A';
                            break;

                        case 'true_or_false':
                            $yourAnswer = ($question_type_answer?->answer) ? 'True' : 'False';
                            break;

                        case 'ranking':
                            $yourAnswer = $question_type_answer ?? 'N/A';
                            break;

                        case 'matching':
                            $yourAnswer = $question_type_answer
                                ? $question_type_answer->map(fn($a) => "{$a->first_item_answer} → {$a->secondd_item_answer}")->implode(', ')
                                : 'N/A';
                            break;
                        case 'coding':
                            $yourAnswer = $answer->codingAnswer;
                            break;
                        default:
                            $yourAnswer = 'Unsupported';
                    }
                }
            }

            $rows[] = [
                'number' => $i + 1,
                'question' => $question->name,
                'question_type' => $question->question_type->getName(),
                'your_answer' => $yourAnswer ?? $answer->is_answered ?? 'Not answered',
                'is_answered'=> $answer->is_answered,
                'score' => $answer->points ?? 0,
                'max_score' => $question->total_points,
                'status' => ($answer->gained_points >= $question->points) ? 'Correct' : 'Incorrect',
                'question_type_answer' => $question_type_answer ?? null
            ];
        }

        // Get subject-topic pairs from exam questions
        $examQuestions = $exam->questions()->with('topic.subject')->get();

        $topics = [];

        foreach ($examQuestions as $question) {
            if (!in_array($question->topic_id, $topics)) {
                $topics[] = $question->topic_id;
            }
        }

        $reviewers = DB::select('
            SELECT reviewers.*, subjects.name as subject_name, topics.name AS topic_name
            FROM reviewers
            INNER JOIN topics ON topics.id = reviewers.topic
            INNER JOIN subjects ON subjects.id = topics.subject_id
            WHERE reviewers.topic IN ('.implode(',', array_fill(0, count($topics), '?')).')
        ', $topics);


        $data = [
            'exam_record' => $examRecord,
            'student_paper' => $student_paper,
            'exam' => $exam,
            'rows' => $rows,
            'reviewers' => $reviewers
        ];

        return view('students/records/show', $data);
    }

    public function showCodingResult(CodingAnswer $codingAnswer){
        $coding_answer_status = Redis::hget('checked_code', $codingAnswer->id);
        $data['status'] = $coding_answer_status;    
        if ($coding_answer_status == 'checked'){
            $data['code_answer'] = $codingAnswer;
            $data['success'] = $codingAnswer->is_code_success;
            $data['test_results'] = json_decode($codingAnswer->test_results);
            $data['failures'] = json_decode($codingAnswer->failures);
            $data['syntax_points'] = $codingAnswer->answer_syntax_points;
            $data['runtime_points'] = $codingAnswer->answer_runtime_points;
            $data['test_case_points'] = $codingAnswer->answer_test_case_points;
            $data['number'] = request()->input('number');
            $data['question'] = request()->input('question');
            $data['score'] = $codingAnswer->answer_syntax_points + $codingAnswer->answer_runtime_points + $codingAnswer->answer_test_case_points;
            $data['max_score'] = request()->input('max_score');

            return view('students/records/get-coding-result', ['data' => $data]);
        } else {
            return response('', 212);
        }
    }

    public function showUpdatedScore(ExamRecord $examRecord){
        if ($examRecord->status != 'in_progress'){
            $student_paper = StudentPaper::findOrFail($examRecord->student_paper_id);
            $examRecord->load('subjects');
        
            $examRecord['max_score'] = request()->input('max_score');

            $student_paper->update(['status'  => 'completed']);

            return view('students/records/get-updated-score', ['exam_record' => $examRecord]);
        } else {
            return response('', 212);
        }
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
}