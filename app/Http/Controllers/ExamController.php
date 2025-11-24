<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAccessCode;
use App\Models\Question;
use App\Services\ExamService;
use App\Services\UserService;
use App\Events\ExamResultsPublished;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        $exams = Exam::with(['courses', 'questions'])
            ->whereHas('courses', function ($query) use ($courseIds) {
                $query->whereIn('courses.id', $courseIds);
            })
            ->paginate(10);
        $header = ['Name', 'Questions', 'Status', 'is Published', 'Examination Date'];
        $rows = $exams->map(function ($exam) {
            return [
                'id' => $exam->id,
                'name' => $exam->name,
                'questions' => $exam->questions->count(),
                'status' => $exam->questions()->sum('total_points') >= $exam->max_score ? 'Complete' : 'Incomplete',
                'is_published' => $exam->is_published ? 'Yes' : 'No',
                'examination date' => Carbon::parse($exam->examination_date)->format('m/d/Y')
            ];
        });

        $data = [
            'headers' => $header,
            'rows' => $rows,
            'models' => $exams,
            'url' => 'exams'
        ];

        if (request()->hasHeader('HX-Request') && !request()->hasHeader('HX-History-Restore-Request')) {
            // Return only the partial view for HTMX
            return view('components/core/index-table', $data);
        }

        return view('exams/index', $data);

    }

    public function show(Exam $exam){
        $exam->load(['questions','courses']);

        return view('exams/show', ['exam' => $exam]);
    }

    public function create(){
        $courses = $this->userService->getCoursesForUser(auth()->user()); 
        return view('exams/create', ['courses' => $courses]);

    }

    public function store(){
        if (!AcademicYear::hasCurrent()) {

            session()->flash('toast', json_encode([
                'status' => 'SYSTEM IS ON MAINTENANCE',
                'message' => 'Currently no Academic Year is set.',
                'type' => 'warning'
            ]));

            return redirect()->route('exams.index');
        } else {
            $academic_year_id = AcademicYear::current()->id;
        }

        $validated = request()->validate([
            'name' => 'required|string|max:255|unique:exams,name',
            'courses' => 'required|array|min:1',
            'courses.*' => 'exists:courses,id|required|string|distinct',
            'max_score' => 'required|integer|gte:1',
            'duration' => 'nullable|integer|min:1',
            'retakes' => 'nullable|integer|min:1',
            'examination_date' => 'nullable|date|after_or_equal:today',
            'passing_score' => 'required|integer|gte:1|max:100',
            'expiration_date' => 'nullable|date|after:now|after_or_equal:examination_date',
        ], [
            'courses' => 'The course field is required.',
            'courses.*' => 'Invalid Course',
            'max_score' => 'The max score field is required',
            'examination_date' => 'The examination date field must be a date after now.',
            'expiration_date.after' => 'The examination date field must be a date after now.',
            'expiration_date.after_or_equal' => 'The expiration date must be on or after the examination date.'
        ]);

        $exam = Exam::create([
            'name' => $validated['name'],
            'academic_year_id' => $academic_year_id,
            'max_score' => $validated['max_score'],
            'duration' => $validated['duration'] ?? null,
            'retakes' => $validated['retakes'] ?? null, 
            'examination_date' => $validated['examination_date'] ?? null,
            'passing_score' => $validated['passing_score'],
            'expiration_date' => $validated['expiration_date'] ?? null,
        ]);

        $exam->courses()->attach($validated['courses']);

        $access_code = $this->examService->generateAccessCode();
        $this->examService->saveAccessCode($exam, $access_code);

        session()->flash('toast', json_encode([
            'status' => 'Created!',
            'message' => 'Exam: ' . $exam->name,
            'type' => 'success'
        ]));

        return redirect(route('exams.show', ['exam' => $exam]));
    }

    public function edit(Exam $exam){
        if ($exam->is_published) {
            return back();
        }
        $exam->load('courses');
        $courses =  $this->userService->getCoursesForUser(auth()->user()); 
        return view('exams/edit', ['exam' => $exam, 'available_courses' => $courses]);
    }

    public function update(Exam $exam){
        $validated = request()->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('subjects', 'name')->ignore($exam->id)],
            'courses' => 'required|array|min:1',
            'courses.*' => 'exists:courses,id|required|string|distinct',
            'max_score' => 'required|integer|gte:1',
            'duration' => 'nullable|integer|min:1',
            'retakes' => 'nullable|integer|min:1',
            'examination_date' => 'nullable|date|after_or_equal:today',
            'passing_score' => 'required|integer|gte:1|max:100',
            'expiration_date' => 'nullable|date|after:now|after_or_equal:examination_date',
        ], [
            'courses' => 'The course field is required.',
            'courses.*' => 'Invalid Course',
            'max_score' => 'The max score field is required',
            'examination_date' => 'The examination date field must be a date after now.',
            'expiration_date.after' => 'The examination date field must be a date after now.',
            'expiration_date.after_or_equal' => 'The expiration date must be on or after the examination date.'
        ]);

        $exam->update([
            'name' => $validated['name'],
            'max_score' => $validated['max_score'],
            'duration' => $validated['duration'] ?? null,
            'retakes' => $validated['retakes'] ?? null, 
            'examination_date' => $validated['examination_date'] ?? null,
            'passing_score' => $validated['passing_score'],
            'expiration_date' => $validated['expiration_date'] ?? null,
        ]);

        $exam->courses()->sync($validated['courses']);

        session()->flash('toast', json_encode([
            'status' => 'Updated!',
            'message' => 'Exam: ' . $exam->name,
            'type' => 'info'
        ]));
        return redirect()->route('exams.show', $exam);

    }

    public function destroy(Exam $exam){
        
        $this->authorize('delete', $exam);

        if ($exam->is_published) {
            return back()->with('error', 'You cannot delete an exam that is currently published.');
        }

        session()->flash('toast', json_encode([
            'status' => 'Destroyed!',
            'message' => 'Exam: ' . $exam->name,
            'type' => 'warning'
        ]));

        $exam->delete();

        return redirect('/exams');
    }

    public function exam_builder_show(Exam $exam){  
        $exam_course = $this->examService->getCourseForExam($exam);
        $exam_questions =  $this->examService->getQuestionsForExam($exam);
        $exam_levels = $this->examService->getQuestionLevelsCountForExam($exam);
        $exam_topics = $this->examService->getTopicsForExam($exam);
        $exam_subjects = $this->examService->getSubjectsForExam($exam);
        $exam_question_types = $this->examService->getQuestionTypeCounts($exam);

        $available_questions = $this->examService->getAvailableQuestionsForExam($exam);
        $questions_header = ['Name', 'Level', 'Type', 'Points'];
        $exam_questions_rows = $this->examService->transformQuestionRows($exam_questions);
        $available_questions_rows = $this->examService->transformQuestionRows($available_questions);
        $data = [
            'exam' => $exam,
            'exam_course' => $exam_course,
            'exam_subjects' => $exam_subjects,
            'exam_topics' => $exam_topics,
            'exam_available_questions' => $available_questions,
            'exam_questions' => $exam_questions,
            'exam_levels' => $exam_levels,
            'exam_question_types' => $exam_question_types,
            'questions_header' => $questions_header,
            'available_questions_rows' => $available_questions_rows,
            'exam_questions_rows' => $exam_questions_rows,
        ];

        return view('exams/exam-builder', $data);
    }

    public function toggle_question(Exam $exam){
        $questions_to_toggle = request()->post('toggle-questions');

        foreach($questions_to_toggle as $question){
        if ($exam->questions->contains($question)) {
            $exam->questions()->detach($question);
        } else {
            $exam->questions()->attach($question);
        }
        }


        $exam_questions =  $this->examService->getQuestionsForExam($exam);
        $exam_levels = $this->examService->getQuestionLevelsCountForExam($exam);
        $exam_topics = $this->examService->getTopicsForExam($exam);
        $exam_subjects = $this->examService->getSubjectsForExam($exam);
        $exam_question_types = $this->examService->getQuestionTypeCounts($exam);

        $available_questions = $this->examService->getAvailableQuestionsForExam($exam);
        $questions_header = ['Name', 'Level', 'Type', 'Points'];
        $exam_questions_rows = $this->examService->transformQuestionRows($exam_questions);
        $available_questions_rows = $this->examService->transformQuestionRows($available_questions);

        $data = [
            'exam' => $exam,
            'exam_subjects' => $exam_subjects,
            'exam_topics' => $exam_topics,
            'exam_available_questions' => $available_questions,
            'exam_questions' => $exam_questions,
            'exam_levels' => $exam_levels,
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
        $access_code = $this->examService->generateAccessCode();
        return view('components/core/partials-exam-access-code', ['exam' => $exam, 'access_code' => $access_code, 'generate' => true]);
    }

    public function getAccessCode(Exam $exam){
        $access_codes = $this->examService->getAccessCodesForExam($exam);
        return view('exams/get-access-codes', ['exam' => $exam ,'access_codes' => $access_codes]);
    }

    public function saveAccessCode(Exam $exam, Request $request){
        $access_code = $request->post('code');
        $is_success =$this->examService->saveAccessCode($exam, $access_code);
        
        if ($is_success !== true) {
            session()->flash('toast', json_encode([
                'status' => 'Error!',
                'message' => 'Access Code Not Saved',
                'type' => 'error'
            ]));
            return back()->withErrors(['access_code' => $is_success]);
            
        }

        $toast =  json_encode([
            'status' => 'Created!',
            'message' => 'Access Code Saved',
            'type' => 'success'
        ]);


        return view('components/core/partials-exam-access-code', 
        ['access_code' => $access_code, 
                'generate' => false, 
                'toast' => $toast
        ]);
    }

    public function destroyAccessCode(Exam $exam, Request $request)
    {
        $access_code = $request->input('code'); 
        $deleted = $exam->accessCodes()
                ->where('access_code', $access_code)
                ->delete();
        if (!$deleted) {
            return back()->withErrors(['access_code' => 'Code not found']);
        }
        
        return response()->noContent();
    }

    public function publishExam(Exam $exam){
        $is_published = $this->examService->attemptToPublish($exam);
        if ($is_published['status'] == false){
            return view('components/core/partials-exam-builder-publish-form-error', [
                'error' => $is_published['error_message']
            ]);        
        }

        if ($is_published) {
        // dispatch immediately
        event(new ExamResultsPublished($exam));
        }

        return response('', 200)->header('HX-Refresh', 'true');
    }

    public function swap_partial_algorithm(Exam $exam){

        return view('exams/partials-algorithms', ['exam'=> $exam]);
    }

    public function swap_tabs(){

        return view('exams/partials-tabs-algorithm');
    }
}
