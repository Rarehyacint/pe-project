<?php

namespace App\Services;
use App\Models\Course;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Question;
class QuestionService
{
    public function getQuestionTypeShow(Question $question){
        $question_type = $question->getTypeModel();
        switch ($question->question_type->value) {
            case 'multiple_choice':
                $choices = $question_type->map(function ($choice) {
                    return [
                        'choice_key' => $choice->choice_key,
                        'item' => $choice->item,
                        'is_correct' => $choice->is_correct,
                    ];
                })->toArray();
                return $choices;

            case 'true_or_false':
                $choices = [
                    'solution' => $question_type->solution,
                ];                
                return $choices;

            case 'identification':
                $choices = [
                    'solution' => $question_type->solution,
                ];                
                return $choices;

            case 'ranking':
                $choices = $question_type->map(function ($choice) {
                    return [
                        'order' => $choice->order,
                        'item' => $choice->item,
                    ];
                })->toArray();
                return $choices;

            case 'matching':
                // MatchingQuestion::create($data);
                break;

            default:
                throw new \Exception("Unknown question type: {$question['question_type']}");
        }
    }
}
