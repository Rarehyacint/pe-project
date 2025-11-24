<?php

namespace App\Http\Controllers\Student;

use App\Models\Exam;
use App\Models\Question;
use App\Factories\StudentAnswerFactory;
use App\Models\StudentPaper;
use App\Services\ExamTakingService;
use DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class StudentAnswerController extends Controller
{
    protected $examTakingService;

    public function __construct(ExamTakingService $examTakingService)
    {
        $this->examTakingService = $examTakingService;
    }

    public function update(StudentPaper $student_paper, Question $question)
    {
        $question_to_answer = session('question_to_answer');
        // authorize using studentpaper, question id
        $student_answer = $student_paper->studentAnswers()->where(['student_paper_id' => $student_paper->id, 'question_id' => $question_to_answer])->first();
        if ($student_answer == null){
            return redirect('/');
        }   

        // update and check student answer here
        $current_attempt = session('current_attempt', 1);
        $answer = request()->post('answer');
        StudentAnswerFactory::update($student_answer, $answer, $current_attempt);
        
        $action = request()->input('action');
        // increment/decrement current position
        match ($action) {
            'back' => $student_paper->decrement('current_position'),
            'next' => $student_paper->increment('current_position'),
            'jump' => $student_paper->update(['current_position' => request('index')]),
            'refresh' => null,
            default => dd($student_paper),
        };

        $is_last_question = $student_paper->current_position >= $student_paper->question_count;

        if ($is_last_question){
            return redirect()->route('exam_records.store', $student_paper);
        } else {
            $data = $this->examTakingService->getCurrentQuestion($student_paper);
            $question_to_jump_id = $data['question']->id;
            session(['question_to_answer' => $question_to_jump_id]);
            $data['student_paper'] = $student_paper;
            $data['is_last_question'] = $is_last_question;
            $data['is_expired'] = $student_paper->isExpired();
            return view( 'students/papers/show', $data);
        }
    }
}
