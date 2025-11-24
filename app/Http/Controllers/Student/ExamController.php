<?php

namespace App\Http\Controllers\Student;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamAccessCode;
use App\Models\Question;
use App\Models\User;
use App\Services\ExamService;
use App\Services\ExamTakingService;
use App\Services\UserService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Events\StudentEnrolled;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Str;

class ExamController extends Controller
{
    protected $userService;
    protected $examService;
    protected $examTakingService;

    public function __construct(UserService $userService, ExamService $examService, ExamTakingService $examTakingService)
    {
        $this->userService = $userService;
        $this->examService = $examService;
        $this->examTakingService = $examTakingService;
    }
    public function index(){
        return redirect()->route('students.index');
    }
    public function show(Exam $exam){
        return view( 'students/exams/show', ['exam'=> $exam]);
    }
    
    public function showExamOverview(Exam $exam)
    {
        $user = auth()->user();
        $student_attempts_left = $this->examTakingService->getAttemptsLeft($exam, $user);
        $student_paper = $this->examTakingService->checkBooleanUnsubmittedExamPaper($exam, $user);

        $data = [
            'exam' => $exam, 
            'attempts_left' => $student_attempts_left,
            'has_unsubmitted_paper' => $student_paper
        ];
        return view('students/exams/get-exam-overview', $data);
    }
    public function store(){
        $user = auth()->user();
        $access_code = request('access-code');
        
        try {
        $exam_access_code = ExamAccessCode::where('access_code', $access_code)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            return view('components/student/access-code-form', [
            'errors' => ['access-code' => ['Code not found.']],
            'old' => ['access-code' => request()->input('access-code')]
            ]);
        }

        $enrolled = $this->examService->enrollAccessCode($user, $exam_access_code);
        if (!$enrolled){
            return view('components/student/access-code-form', [
            'errors' => ['access-code' => ['Already enrolled in this Exam.']],
            'old' => ['access-code' => request()->input('access-code')]
            ]);
        }

        // dispatch enrollment event so listeners can create notifications, etc.
        $exam = $exam_access_code->exam ?? null;
        if ($exam) {
            event(new StudentEnrolled($user, $exam));
        }

        return response('', 200)->header('HX-Refresh', 'true');
    }

}