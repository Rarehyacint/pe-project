<?php

namespace App\Services;
use App\Models\Course;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Question;
use Illuminate\Support\Facades\Http;
use Storage;
use Str;
class QuestionTypeService
{
    public function storeMultipleChoice(Question $question, array $question_type_data){
        $choice_keys = ['a','b','c','d'];
        $question_type_data['items'] = array_combine($choice_keys,  $question_type_data['items']);
        foreach($question_type_data['items'] as $key => $item){
            $question->multipleChoiceQuestions()->create([
                'choice_key' => $key,
                'item' => $item,
                'is_solution' => $key == $question_type_data['solution'],
                'points' => $question_type_data['points']
            ]);
        }   
    }
    public function storeTrueOrFalse(Question $question, array $question_type_data){
        $question->trueOrFalseQuestion()->create([
            'solution' => $question_type_data['solution'],
            'points' => $question_type_data['points']
        ]);
    }
    public function storeIdentification(Question $question, array $question_type_data){
        $question->identificationQuestion()->create([
            'solution' => $question_type_data['solution'],
            'points' => $question_type_data['points']
        ]);
    }
    public function storeRanking(Question $question, array $question_type_data){
        $order = 1;
        foreach ($question_type_data['items'] as $item) {
            $question->rankingQuestions()->create([
                'order' => $order,
                'item' => $item['solution'],
                'item_points' => $item['points']
            ]);
            $order++;
        }    
    }
    public function storeMatching(Question $question, array $question_type_data){
        foreach($question_type_data['items'] as $item){
            $question->matchingQuestions()->create([
                'first_item' => $item['left'],
                'second_item' => $item['right'],
                'item_points' => $item['points']
            ]);                    
        }
    }
    public function storeCoding(Question $question, array $question_data, $coding_question_data, array $coding_question_language_data){
        $slug_name = Str::slug($question_data['name']);
        $folder = "codingQuestions/{$question->id}_{$slug_name}/";
        Storage::makeDirectory($folder);
        
        $coding_question = $question->codingQuestion()->create([
            'instruction' => $coding_question_data['instruction'],
            'is_syntax_code_only' => $coding_question_data['is_syntax_code_only'] ? true : false,
            'enable_compilation' => $coding_question_data['enable_compilation'] ? true : false,
            'syntax_points' => $coding_question_data['syntax_points'],
            'runtime_points' => $coding_question_data['runtime_points'],
            'test_case_points' => $coding_question_data['test_case_points'],
            'syntax_points_deduction_per_error' => $coding_question_data['syntax_points_deduction'],
            'runtime_points_deduction_per_error' => $coding_question_data['runtime_points_deduction'],
            'test_case_points_deduction_per_error' => $coding_question_data['test_case_points_deduction']
        ]);

        
        
        foreach ($coding_question_language_data as $language => $codes) {
            $language_folder = "{$folder}supportedLanguages/{$language}/";
            Storage::makeDirectory($language_folder);
            $ext = self::getExtension($language);

            $complete_solution_name = self::getClassName($codes['complete_solution'], $language);
            $test_case_name =  self::getClassName($codes['test_case'], $language);

            $complete_solution_file_path = "{$language_folder}{$complete_solution_name}.{$ext}";
            $initial_solution_file_path = "{$language_folder}initial_solution.{$ext}";
            $test_case_file_path = "{$language_folder}{$test_case_name}.{$ext}";

            Storage::put($complete_solution_file_path, $codes['complete_solution']);
            Storage::put($initial_solution_file_path, $codes['initial_solution']);
            Storage::put($test_case_file_path, $codes['test_case']);

            $coding_question->codingQuestionLanguages()->create([
                'language' => $language,
                'complete_solution_file_path' => $complete_solution_file_path,
                'initial_solution_file_path' => $initial_solution_file_path,
                'test_case_file_path' => $test_case_file_path,
                'class_name' => $complete_solution_name,
                'test_class_name' => $test_case_name
            ]);
        }
    }

    public function updateMultipleChoice(Question $question, array $question_type_data){
        Self::prepareUpdateQuestion($question);
        $choice_keys = ['a', 'b', 'c', 'd'];
        $items = array_combine($choice_keys, $question_type_data['items']);
        foreach ($items as $key => $item) {
            $question->multipleChoiceQuestions()->create([
                'choice_key' => $key,
                'item' => $item,
                'is_solution' => $key == $question_type_data['solution'],
                'points' => $question_type_data['points']
            ]);
        }
    }
    public function updateTrueOrFalse(Question $question, array $question_type_data, array $previous_data,  $previous_question_type){
        if ($previous_question_type != $previous_data['type']){
            Self::prepareUpdateQuestion($question);
        }
        $question->trueOrFalseQuestion()->updateOrCreate(['question_id' => $question->id], 
        [
            'solution' => $question_type_data['solution'],
            'points' => $question_type_data['points']
        ]);
    }
    public function updateIdentification(Question $question, array $question_type_data, array $previous_data,  $previous_question_type){
        if ($previous_question_type != $previous_data['type']){
            Self::prepareUpdateQuestion($question);
        }
        $question->identificationQuestion()->updateOrCreate(['question_id' => $question->id], 
        [
            'solution' => $question_type_data['solution'],
            'points' => $question_type_data['points']
        ]);
    }
    public function updateRanking(Question $question, array $question_type_data){
        Self::prepareUpdateQuestion($question);
        $order = 1;
        foreach ($question_type_data['items'] as $item) {
            $question->rankingQuestions()->create([
                'order' => $order,
                'item' => $item['solution'],
                'item_points' => $item['points']
            ]);
            $order++;
        }
    }
    public function updateMatching(Question $question, array $question_type_data){
       Self::prepareUpdateQuestion($question);
        foreach($question_type_data['items'] as $item){
            $question->matchingQuestions()->create([
                'first_item' => $item['left'],
                'second_item' => $item['right'],
                'item_points' => $item['points']
            ]);                    
        }
    }
    public function updateCoding(Question $question, array $coding_question_data){
        Self::prepareUpdateQuestion($question);
        $language_data = json_decode($coding_question_data['supported_languages'], true);
        $slug_name = Str::slug($coding_question_data['name']);
        $folder = "codingQuestions/{$question->id}_{$slug_name}/";
        Storage::makeDirectory($folder);
       $coding_question = $question->codingQuestion()->create([
            'instruction' => $coding_question_data['instruction'],
            'is_syntax_code_only' => $coding_question_data['syntax_only_checkbox'] ?? false,
            'enable_compilation' => $coding_question_data['enable_student_compile'] ?? false,
            'syntax_points' => $coding_question_data['syntax_points'],
            'runtime_points' => $coding_question_data['runtime_points'],
            'test_case_points' => $coding_question_data['test_case_points'],
            'syntax_points_deduction_per_error' => $coding_question_data['syntax_points_deduction'],
            'runtime_points_deduction_per_error' => $coding_question_data['runtime_points_deduction'],
            'test_case_points_deduction_per_error' => $coding_question_data['test_case_points_deduction']
        ]);

        foreach ($language_data as $language => $codes) {
            $language_folder = "{$folder}supportedLanguages/{$language}/";
            Storage::makeDirectory($language_folder);
            $ext = self::getExtension($language);

            $complete_solution_name = self::getClassName($codes['complete_solution'], $language);
            $test_case_name =  self::getClassName($codes['test_case'], $language);

            $complete_solution_file_path = "{$language_folder}{$complete_solution_name}.{$ext}";
            $initial_solution_file_path = "{$language_folder}initial_solution.{$ext}";
            $test_case_file_path = "{$language_folder}{$test_case_name}.{$ext}";

            Storage::put($complete_solution_file_path, $codes['complete_solution']);
            Storage::put($initial_solution_file_path, $codes['initial_solution']);
            Storage::put($test_case_file_path, $codes['test_case']);

            $coding_question->codingQuestionLanguages()->create([
                'language' => $language,
                'complete_solution_file_path' => $complete_solution_file_path,
                'initial_solution_file_path' => $initial_solution_file_path,
                'test_case_file_path' => $test_case_file_path,
                'class_name' => $complete_solution_name,
                'test_class_name' => $test_case_name
            ]);
        }
    }
    private static function getClassName(string $code, string $language): string
    {
        $patterns = [
            'java' => '/\bclass\s+(\w+)/',
            'c++' => '/\b(?:class|struct)\s+(\w+)/',
            'python' => '/^[ \t]*class\s+(\w+)/m',
        ];

        $pattern = $patterns[strtolower($language)] ?? '/\bclass\s+(\w+)/';
        $class_name = 'tmp';

        if (preg_match($pattern, $code, $matches)) {
            $class_name = $matches[1];
        }

        return $class_name;
    }

    private static function getExtension(string $language): string
    {
        return match (strtolower($language)) {
            'java' => 'java',
            'python' => 'py',
            'c++' => 'cpp',
            default => 'txt',
        };
    }
    private static function prepareUpdateQuestion(Question $question)
    {
        switch ($question->question_type->value) {
            case 'multiple_choice':
                $question->multipleChoiceQuestions()->delete();
                break;
                
            case 'true_or_false':
                $question->trueOrFalseQuestion()->delete();
                break;

            case 'identification':
                $question->identificationQuestion()->delete();
                break;

            case 'ranking':
                $question->rankingQuestions()->delete();
                break;

            case 'matching':
                $question->matchingQuestions()->delete();
                break;

            case 'coding':
                if ($question->codingQuestion) {
                    $question->codingQuestion->codingQuestionLanguages()->delete();
                    $question->codingQuestion()->delete(); 
                    $old_slug_name = Str::slug($question['name']);
                    $old_folder = "codingQuestions/{$question->id}_{$old_slug_name}/";
                    Storage::deleteDirectory($old_folder);
                    }
                break;

            default:
            throw new \Exception("Unsupported question type: {$question->question_type->value}");
        }
    }
}
