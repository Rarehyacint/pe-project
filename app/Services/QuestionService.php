<?php

namespace App\Services;
use App\Models\Course;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Question;
use Illuminate\Support\Facades\Http;
use Storage;
use Str;
class QuestionService
{
    public function getQuestionTypeShow(Question $question){
        $question_type = $question->getTypeModel();
        switch ($question->question_type->value) {
            case 'multiple_choice':
                $choices['choices'] = $question_type->map(function ($choice) {
                    return [
                        'choice_key' => $choice->choice_key,
                        'item' => $choice->item,
                        'is_solution' => $choice->is_solution,
                    ];
                })->toArray();
                $choices['points'] = $question->total_points;
                return $choices;

            case 'true_or_false':
                $choices = [
                    'solution' => $question_type->solution,
                    'points' => $question->total_points
                ];                
                return $choices;

            case 'identification':
                $choices = [
                    'solution' => $question_type->solution,
                    'points' => $question->total_points
                ];                
                return $choices;

            case 'ranking':
                $choices = $question_type->map(function ($choice) {
                    return [
                        'order' => $choice->order,
                        'solution' => $choice->item,
                        'item_points' => $choice->item_points
                    ];
                })->toArray();
                return $choices;

            case 'matching':
                $choices = $question_type->map(function ($choice) {
                    return [
                        'left' => $choice->first_item,
                        'right' => $choice->second_item,
                        'item_points' => $choice->item_points

                    ];
                })->toArray();
                return $choices;

            case 'coding':
                $instruction = Str::of($question_type->instruction)->markdown([
                                    'html_input' => 'strip',
                                ]);
                $instruction_raw = $question_type->instruction;
                $is_syntax_code_only = $question_type->is_syntax_code_only;
                $enable_compilation = $question_type->enable_compilation;
                $syntax_points = $question_type->syntax_points;
                $runtime_points = $question_type->runtime_points;
                $test_case_points = $question_type->test_case_points;
                $syntax_points_deduction_per_error = $question_type->syntax_points_deduction_per_error;
                $runtime_points_deduction_per_error = $question_type->runtime_points_deduction_per_error;
                $test_case_points_deduction_per_error = $question_type->test_case_points_deduction_per_error;
                            
                $languages = $question_type->codingQuestionLanguages()->pluck('language');
                $coding_languages = $question_type->codingQuestionLanguages;            
                $language_codes = $coding_languages->mapWithKeys(function ($item) {
                    return [
                        $item->language => [
                            'complete_solution' => Storage::get($item->complete_solution_file_path),
                            'initial_solution'  => Storage::get($item->initial_solution_file_path),
                            'test_case'         => Storage::get($item->test_case_file_path),
                        ]
                    ];
                })->toArray();

                $data = [
                    'instruction' => $instruction,
                    'is_syntax_code_only' => $is_syntax_code_only,
                    'enable_compilation' => $enable_compilation,
                    'syntax_points' => $syntax_points,
                    'runtime_points' => $runtime_points,
                    'test_case_points' => $test_case_points,
                    'syntax_points_deduction_per_error' => $syntax_points_deduction_per_error,
                    'runtime_points_deduction_per_error' => $runtime_points_deduction_per_error,
                    'test_case_points_deduction_per_error' => $test_case_points_deduction_per_error,
                    'instruction_raw' =>  $instruction_raw,
                    'languages' => $languages,
                    'language_codes' =>$language_codes
                ];
                
                return $data;

            default:
                throw new \Exception("Unknown question type: {$question['question_type']}");
        }
    }

    public static function validate(string $language, string $code, string $test, $code_settings): array
    {
        switch ($language) {
            case 'java':
                try {
                    $response = Http::timeout(30)->post('http://java-api:8090/execute', [
                        'code' => $code,
                        'testUnit' => $test,
                        'syntax_coding_question_only' => $code_settings['syntax_coding_question_only'],
                        'request_action' => $code_settings['action'],
                        'syntax_points' => $code_settings['syntax_points'],
                        'runtime_points' => $code_settings['runtime_points'],
                        'test_case_points' => $code_settings['test_case_points'],
                        'syntax_points_deduction' => $code_settings['syntax_points_deduction'],
                        'runtime_points_deduction' => $code_settings['runtime_points_deduction'],
                        'test_case_points_deduction' => $code_settings['test_case_points_deduction']
                    ]);
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        return $data;
                    }
                    return ['success' => false, 'error' => 'API returned error'];
                } catch (\Exception $e) {
                    return ['success' => false, 'error' => 'API unreachable | API may not be available', 'exception' => $e->getMessage()];
                }

            default:
                return ['success' => false, 'error' => 'Unsupported language'];
        }
    }
}
