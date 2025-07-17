<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Exam;
use App\Models\Question;
use App\Services\ExamService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Str;

class ExamController extends Controller
{
    protected $userService;
    protected $examService;

    public function __construct(UserService $userService, ExamService $examService)
    {
        $this->userService = $userService;
        $this->examService = $examService;
    }

    public function index(){
        $courseIds = $this->userService->getCoursesForUser(auth()->user())->pluck('id');
        $exams = Exam::whereIn('course_id', $courseIds)->paginate(10);

        $header = ['ID', 'Name', 'Course', 'Questions', 'Status', 'is Published', 'Examination Date'];
        $rows = $exams->map(function ($exam) {
            return [
                'id' => $exam->id,
                'name' => $exam->name,
                'course' => $exam->course->name,
                'questions' => $exam->questions->count(),
                'status' => $exam->questions()->sum('points') >= $exam->max_score ? 'Complete' : 'Incomplete',
                'is_published' => $exam->is_published ? 'Yes' : 'No',
                'examination date' => Carbon::parse($exam->examination_date)->format('m/d/Y')
            ];
        });

        $data = [
            'headers' => $header,
            'rows' => $rows,
            'exams' => $exams
        ];

        return view('exams/index', $data);

    }

    public function show(Exam $exam){
        return view('exams/show', ['exam' => $exam]);
    }

    public function create(){
        $courses = Course::all()->pluck('name', 'id');
        return view('exams/create', ['courses' => $courses]);

    }

    public function store(){
        request()->validate([
            'name' => 'required|string|max:255',
            'course_id' => 'required|exists:courses,id',
            'max_score' => 'required|integer|gte:1',
            'duration' => 'nullable|integer|min:1',
            'retakes' => 'nullable|integer|min:1',
            'examination_date' => 'nullable|date|after:now',
        ], [
            'course_id' => 'The course field is required.',
            'max_score' => 'The max score field is required',
            'examination_date' => 'The examination date field must be a date after now.'
        ]);
        $accessCode = strtoupper(implode('-', str_split(Str::random(8), 4)));
        Exam::create([
            'name' => request('name'),
            'course_id' => request('course_id'),
            'access_code' => $accessCode,
            'max_score' => request('max_score'),
            'duration' => request('duration') ?? null,
            'retakes' => request('retakes') ?? null, 
            'examination_date' => request('examination_date'),
        ]);

        return redirect('/exams');
    }

    public function edit(Exam $exam){
        return view('exams/edit', ['exam'=>$exam]);

    }

    public function update(Exam $exam){
        request()->validate([
            'name' => 'required|string|max:255',
            'max_score' => 'required|integer|gte:1',
            'duration' => 'nullable|integer|min:1',
            'retakes' => 'nullable|integer|min:1',
            'examination_date' => 'nullable|date|after:now',
        ], [
            'max_score' => 'The max score field is required',
            'examination_date' => 'The examination date field must be a date after now.'
        ]);

        $exam->update([
            'name' => request('name'),
            'max_score' => request('max_score'),
            'duration' => request('duration') ?? null,
            'retakes' => request('retakes') ?? null, 
            'examination_date' => request('examination_date') ?? null,
        ]);
        return redirect()->route('exams.show', $exam);

    }

    public function destroy(Exam $exam){
        
        $this->authorize('delete', $exam);

        $exam->delete();

        return redirect('/exams');
    }

    public function exam_builder_show(Exam $exam){
        $exam_course = $this->examService->getCourseForExam($exam);
        $exam_questions =  $this->examService->getQuestionsForExam($exam);
        $exam_topics = $this->examService->getTopicsForExam($exam);
        $exam_subjects = $this->examService->getSubjectsForExam($exam);
        $exam_question_types = $this->examService->getQuestionTypeCounts($exam);

        $available_questions = $this->examService->getAvailableQuestionsForExam($exam);
        $questions_header = ['ID', 'Name', 'Subject', 'Topic', 'Type'];
        $exam_questions_rows = $this->examService->transformQuestionRows($exam_questions);
        $available_questions_rows = $this->examService->transformQuestionRows($available_questions);
        $data = [
            'exam' => $exam,
            'exam_course' => $exam_course,
            'exam_subjects' => $exam_subjects,
            'exam_topics' => $exam_topics,
            'exam_available_questions' => $available_questions,
            'exam_questions' => $exam_questions,
            'exam_question_types' => $exam_question_types,
            'questions_header' => $questions_header,
            'available_questions_rows' => $available_questions_rows,
            'exam_questions_rows' => $exam_questions_rows,
        ];

        return view('exams/exam-builder', $data);
    }

    public function toggle_question(Exam $exam, Question $question){
        if ($exam->questions->contains($question->id)) {
            $exam->questions()->detach($question->id);
        } else {
            $exam->questions()->attach($question->id);
        }

        $exam_questions =  $this->examService->getQuestionsForExam($exam);
        $exam_topics = $this->examService->getTopicsForExam($exam);
        $exam_subjects = $this->examService->getSubjectsForExam($exam);
        $exam_question_types = $this->examService->getQuestionTypeCounts($exam);

        $available_questions = $this->examService->getAvailableQuestionsForExam($exam);
        $questions_header = ['ID', 'Name', 'Subject', 'Topic', 'Type'];
        $exam_questions_rows = $this->examService->transformQuestionRows($exam_questions);
        $available_questions_rows = $this->examService->transformQuestionRows($available_questions);

        $data = [
            'exam' => $exam,
            'exam_subjects' => $exam_subjects,
            'exam_topics' => $exam_topics,
            'exam_available_questions' => $available_questions,
            'exam_questions' => $exam_questions,
            'exam_question_types' => $exam_question_types,
            'questions_header' => $questions_header,
            'available_questions_rows' => $available_questions_rows,
            'exam_questions_rows' => $exam_questions_rows
        ];

        return view('components/core/partials-exam-builder', $data);
    }

    public function build_exam(Exam $exam){
        $algorithm = request()->query('algorithm');
        $subject_weight = (request()->query('subject_weight') ?: 60) / 100;
        $criteria = request()->query('criteria') ?: 'density';

        $optimal_set_of_questions = match ($algorithm) {
                'greedy' => $this->examService->useGreedyAlgorithm($exam, $subject_weight, $criteria),
                'dynamic_programming' => $this->examService->useDynamicProgramming($exam, $subject_weight, $criteria),
                default => $this->examService->useDynamicProgramming($exam, $subject_weight, $criteria),
            };
        $questions_to_sync = array_column($optimal_set_of_questions['questions'], 'id');
        $exam->questions()->sync($questions_to_sync);
        $exam->update(['applied_algorithm' => $optimal_set_of_questions['algorithm']]);

        return response('', 200)->header('HX-Refresh', 'true');    
    }

    public function generateAccessCode(Exam $exam){
        $accessCode = strtoupper(implode('-', str_split(Str::random(8), 4)));
        $exam->update(['access_code' => $accessCode]);
        return view('components/core/partials-exam-access-code', ['exam' => $exam]);
    }

    public function publishExam(Exam $exam){
        // // validate this to look if there's an examination date
        // $exam->update(['access_code' => $accessCode]);
        // return view('components/core/partials-exam-access-code', ['exam' => $exam]);
    }

    public function swap_partial_algorithm(Exam $exam){

        return view('exams/partials-algorithms', ['exam'=> $exam]);
    }

    public function swap_tabs(){

        return view('exams/partials-tabs-algorithm');
    }
}
