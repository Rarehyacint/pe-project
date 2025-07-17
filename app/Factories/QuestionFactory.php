<?php

namespace App\Factories;

use App\Models\Question;
use App\Models\MultipleChoiceQuestion;
use App\Models\TrueOrFalseQuestion;
use App\Models\IdentificationQuestion;
use App\Models\RankingQuestion;
use App\Models\MatchingQuestion;
use Illuminate\Support\Facades\DB;

class QuestionFactory
{
    public static function create(array $data)
    {
        $question_data = [
            'topic_id' => (int) $data['topic'],
            'question_type' => $data['type'],
            'name' => $data['name'],
            'points' => $data['points']
        ];
        $question_type_data = [
            'items' => $data['items'] ?? [],
            'solution' => $data['solution']
        ];    

        \Log::info('Question Data', $question_data);
        \Log::info('Question Type Data', $question_type_data);
        
        DB::beginTransaction();

        try {
            $question = Question::create($question_data);

            switch ($question_data['question_type']) {
                case 'multiple_choice':
                    $choice_keys = ['a','b','c','d'];
                    $question_type_data['items'] = array_combine($choice_keys,  $question_type_data['items']);
                    foreach($question_type_data['items'] as $key => $item){
                        $question->multipleChoiceQuestions()->create([
                            'choice_key' => $key,
                            'item' => $item,
                            'is_correct' => $key == $question_type_data['solution']
                        ]);
                    }   
                    DB::commit();
                    break;
                case 'true_or_false':
                    $question->trueOrFalseQuestion()->create(['solution' => $question_type_data['solution']]);
                    DB::commit();
                    break;
                case 'identification':
                    $question->identificationQuestion()->create(['solution' => $question_type_data['solution']]);
                    DB::commit();
                    break;

                case 'ranking':
                    switch ($question_type_data['solution']){
                        case 'ascending':
                            $ascending_solution = array_reverse($question_type_data['items']);

                            $order = 1;
                            foreach($ascending_solution as $item){
                            $question->rankingQuestions()->create([
                                'order' => $order, 
                                'item' => $item]);
                            $order++;

                            }      
                            DB::commit();

                            break;

                        case 'descending':
                            $order = 1;
                            foreach($question_type_data['items'] as $item){
                                $question->rankingQuestions()->create([
                                    'order' => $order, 
                                    'item' => $item]);
                                $order++;

                                } 
                                DB::commit();

                                break;       

                        default:
                        throw new \Exception("Unknown ranking solution: {$question_type_data['solution']}");
                    }
                    break;

                case 'matching':
                    foreach($question_type_data['items'] as $item){
                        \Log::info('Question Type items', [$item]);
                    }
                    // MatchingQuestion::create($data);
                    break;

                default:
                    throw new \Exception("Unknown question type: {$question_data['question_type']}");
            }
        } catch (\Exception $e) {
            DB::rollBack(); 
            throw $e;
        }
    }
}
