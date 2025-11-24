<?php

namespace App\Http\Controllers\Student;

use App\Models\Exam;
use App\Models\ExamRecord;
use App\Models\StudentPaper;
use App\Services\ExamService;
use App\Services\ExamTakingService;
use Cache;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class StudentPaperController extends Controller
{
    protected $examTakingService;

    public function __construct(ExamTakingService $examTakingService)
    {
        $this->examTakingService = $examTakingService;
    }

    public function takeExam(Exam $exam)
    {
        $user = auth()->user();

        // validate User if allowed to take exam
        $can_take_exam = $this->examTakingService->validateExamAccess($exam, $user);
        if (!$can_take_exam){
            return redirect('/student');
        }

        // Check if the user has an existing exam paper that's not expired or submitted
        $student_paper = $this->examTakingService->checkUnsubmittedExamPaper($exam, $user);
        
        if($student_paper->isExpired()){
            $exam_result = $this->examTakingService->submitPaper($student_paper, $user);
            return response('', 204)->header('HX-Redirect', route('exam_records.show', ['exam' => $exam_result['exam'], 'exam_record' => $exam_result['exam_record']]));
        }

        if($student_paper->expired_at != null){
            $student_paper_duration = $student_paper->getRemainingDuration();
        } else {
            $student_paper_duration = null;
        }

        $question_to_show = $this->examTakingService->getCurrentQuestion($student_paper);
        $question_to_show_id = $question_to_show['question']->id;
        session(['question_to_answer' => $question_to_show_id]);

        $data = [
            'student_paper' => $student_paper,  
            'duration' => $student_paper_duration,
            'expired_date' => $student_paper->expired_at,
            'exam' => $exam,
        ];

        

        return view( 'students/papers/layout-take-exam', $data);
    }

    public function show(StudentPaper $student_paper){
        if ($student_paper->current_position < 0){
            $student_paper->update(['current_position' => 0]);
        }
        $data = $this->examTakingService->getCurrentQuestion($student_paper);
        $data['student_paper'] = $student_paper;
        $data['is_expired'] = $student_paper->isExpired();
        return view( 'students/papers/show', $data);
    }

    public function loadQuestionLinks(Exam $exam, StudentPaper $student_paper){
        $questions = $this->examTakingService->orderedQuestions($student_paper, $exam);
        return view('students/papers/question-links', ['questions_in_array' => $questions, 'student_paper' => $student_paper]);
    }
    public function pollToAutoCompletedExamRecord(StudentPaper $student_paper){
        $exam_result = $this->examTakingService->getExamRecordFromStudentPaper($student_paper);
        if (!$exam_result){
            return response(false);
        } 

        $exam = $exam_result['exam'];
        $record = $exam_result['exam_record']->sortByDesc('id')->first();
        
        return response('', 204)
            ->header('HX-Redirect', route('exam_records.show', [
                'exam' => $exam->id,
                'exam_record' => $record->id,
            ]));
    }
}
