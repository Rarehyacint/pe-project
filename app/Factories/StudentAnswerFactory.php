<?php

namespace App\Factories;

use App\Models\Question;
use App\Models\StudentAnswer;
use App\Services\AnswerService;
use App\Services\QuestionService;
use App\Services\QuestionTypeService;
use Cache;
use Illuminate\Support\Facades\DB;

class StudentAnswerFactory
{
    public static function update(StudentAnswer $student_answer, $data, int $attempt_count){
        $student_answer_service = new AnswerService();
        $answer = $data ?? null;
        if ($student_answer_service->hasAnswerChanged($student_answer, $answer)) {
            DB::transaction(function () use ($student_answer, $answer, $attempt_count, $student_answer_service) {
            // load question and question type
            $question_service = new QuestionService();
            $question = $student_answer->question;
            $question_type = $question_service->getQuestionTypeShow($question);
            $update_student_answer = [];
            switch ($question->question_type->value){
                case('multiple_choice'):
                    $update_student_answer = $student_answer_service->storeMultipleChoice($student_answer, $answer, $question_type);
                    break;
                case('true_or_false'):
                    $update_student_answer = $student_answer_service->storeTrueOrFalse($student_answer, $answer, $question_type, $question);
                    break;
                case('identification'):
                    $update_student_answer = $student_answer_service->storeIdentification($student_answer, $answer, $question_type, $question);
                    break;
                case('ranking'):
                    $update_student_answer = $student_answer_service->storeRanking($student_answer, $answer, $question_type, $question);
                    break;
                case('matching'):
                    $update_student_answer = $student_answer_service->storeMatching($student_answer, $answer, $question_type, $question);
                    break;
                case('coding'):
                    $update_student_answer = $student_answer_service->storeCoding($student_answer, $answer, $question, $attempt_count);
                    break;
            }
            $student_answer->update([
                'points' =>  $update_student_answer['total_points'],
                'is_answered' => $answer ? true : false,
                'is_correct' =>  $update_student_answer['is_correct'],
                'first_answered_at' => $answer ? now() : null
                ]);
            });
        } else {
           
        }
    }
}
