<?php

namespace App\Factories;

use App\Models\Question;
use App\Models\Tag;
use App\Services\QuestionTypeService;
use Illuminate\Support\Facades\DB;

class QuestionFactory
{

    public static function create(array $data)
    {
        $question_data = [
            'topic_id' => (int) $data['topic'],
            'question_type' => $data['type'],
            'total_points' => $data['points'],
            'name' => $data['name']
        ];   

        $question_tags = [
            'question_level' => $data['question_level'],
            'optional_tags' => $data['optional_tags']
        ];
       
        DB::transaction(function () use ($question_data, $question_tags, $data) {
            $question = Question::create($question_data);
            $question_type_service = new QuestionTypeService();
            if ($question_data['question_type'] == 'coding'){
                        $coding_question_data = [
                                    'instruction' => $data['instruction'],
                                    'is_syntax_code_only' => $data['syntax_only_checkbox']  ?? false,
                                    'enable_compilation' => $data['enable_student_compile']  ?? false,
                                    'syntax_points' => $data['syntax_points'],
                                    'runtime_points' => $data['runtime_points'],
                                    'test_case_points' => $data['test_case_points'],
                                    'syntax_points_deduction' => $data['syntax_points_deduction'],
                                    'runtime_points_deduction' => $data['runtime_points_deduction'],
                                    'test_case_points_deduction' => $data['test_case_points_deduction'],
                                ];
                            $coding_question_language_data = json_decode($data['supported_languages'], true);
                            $question_type_service->storeCoding(
                                    $question, $question_data, $coding_question_data, $coding_question_language_data
                            );
            } else {
                $question_type_data = [
                    'items' => $data['items'] ?? [],
                    'solution' => $data['solution'] ?? '',
                    'points' => $data['points'] ?? ''
                    ]; 
                match ($question_data['question_type']) {
                    'multiple_choice' => $question_type_service->storeMultipleChoice($question, $question_type_data),
                    'true_or_false' => $question_type_service->storeTrueOrFalse($question, $question_type_data),
                    'identification' => $question_type_service->storeIdentification($question, $question_type_data),
                    'ranking' => $question_type_service->storeRanking($question, $question_type_data),
                    'matching' => $question_type_service->storeMatching($question, $question_type_data),
                    default => throw new \InvalidArgumentException("Unknown question type: {$question_data['question_type']}"),
                };
            }

            $question_level = Tag::firstOrCreate(['name' => $question_tags['question_level']]);

            // Detach any existing required tag (if enforcing only one)
            // $question->tags()->wherePivot('type', 'required')->detach();

            $question->tags()->attach($question_level->id, ['type' => 'required']);

            if (!empty($question_tags['optional_tags'])) {
                $optional_tags = [];

                foreach ($question_tags['optional_tags'] as $tags) {
                    $tag = Tag::firstOrCreate(['name' => $tags]);

                    $optional_tags[$tag->id] = ['type' => 'optional'];
                }

                if (!empty($optional_tags)) {
                    $question->tags()->attach($optional_tags);
                }
            }
            session()->flash('toast', json_encode([
                'status' => 'Created!',
                'message' => 'Question: ' . $question->name,
                'type' => 'success'
            ]));
        });


    }

    public static function update(Question $question, array $data)
    {
        DB::transaction(function () use ($question, $data) {
            $previous_question_type = $question->question_type->value;
            $question_type_service = new QuestionTypeService();

            $question_tags = [
                'question_level' => $data['question_level'],
                'optional_tags' => $data['optional_tags']
            ];

            if ($data['type'] == 'coding'){
                $question_type_service->updateCoding($question, $data);
            } else {
                $question_type_data = [
                    'items' => $data['items'] ?? [],
                    'solution' => $data['solution'] ?? '',
                    'points' => $data['points'] ?? ''
                ]; 
                match ($data['type']) {
                    'multiple_choice' => $question_type_service->updateMultipleChoice($question, $question_type_data),
                    'true_or_false' => $question_type_service->updateTrueOrFalse($question, $question_type_data, $data, $previous_question_type),
                    'identification' => $question_type_service->updateIdentification($question, $question_type_data, $data,  $previous_question_type),
                    'ranking' => $question_type_service->updateRanking($question, $question_type_data),
                    'matching' => $question_type_service->updateMatching($question, $question_type_data),
                    default => throw new \InvalidArgumentException("Unknown question type: {$data['question_type']}"),
                };
            }
            $question->update([
                'topic_id' => (int) $data['topic'],
                'question_type' => $data['type'],
                'name' => $data['name'],
                'total_points' => $data['points']
            ]);

            $question_level = Tag::firstOrCreate(['name' => $question_tags['question_level']]);
            $question_level_tag = [$question_level->id => ['type' => 'required']];
            $optional_tags_sync = [];

            if (!empty($question_tags['optional_tags'])) {
                foreach ($question_tags['optional_tags'] as $tagName) {
                    $tag = Tag::firstOrCreate(['name' => $tagName]);
                    $optional_tags_sync[$tag->id] = ['type' => 'optional'];
                }
            }

            $question->tags()->wherePivot('type', 'required')->detach();
            $question->tags()->attach($question_level_tag);

            $question->tags()->wherePivot('type', 'optional')->detach();

            if (!empty($optional_tags_sync)) {
                $question->tags()->attach($optional_tags_sync);
            }

            session()->flash('toast', json_encode([
                'status' => 'Updated!',
                'message' => 'Question: ' . $question->name,
                'type' => 'info'
            ]));

        });
    }

    public static function createFakeData(array $data, int $user_id)
        {
            if ($data['type'] == 'ranking' || $data['type'] == 'matching'){
                $items = $data['items'];
                $totalPoints = 0;

                foreach ($items as $item) {
                    if (isset($item['points']) && is_numeric($item['points'])) {
                        $totalPoints += (int) $item['points'];
                    }
                }
                $data['points'] = $totalPoints;
            }
            $question_data = [
                'topic_id' => (int) $data['topic'],
                'question_type' => $data['type'],
                'name' => $data['name'],
                'total_points' => $data['points'],
                'created_by' => $user_id,
                'updated_by' => $user_id,
            ];   

            DB::transaction(function () use ($question_data, $data) {
                $question = Question::create($question_data);

                $question_level = Tag::firstOrCreate(['name' => $data['question_level']]);
                $question_level_tag = [$question_level->id => ['type' => 'required']];
                $question->tags()->wherePivot('type', 'required')->detach();
                $question->tags()->attach($question_level_tag);

                $question_type_service = new QuestionTypeService();
                if ($question_data['question_type'] == 'coding'){
                    $coding_question_data = [
                        'instruction' => $data['instruction'],
                        'syntax_points' => $data['syntax_points'],
                        'runtime_points' => $data['runtime_points'],
                        'test_case_points' => $data['test_case_points'],
                    ];
                    $coding_question_language_data = json_decode($data['supported_languages'], true);
                    $question_type_service->storeCoding(
                            $question, $question_data, $coding_question_data, $coding_question_language_data
                    );
                } else {
                    $question_type_data = [
                    'items' => $data['items'] ?? [],
                    'solution' => $data['solution'] ?? '',
                    'points' => $data['points'] ?? ''
                    ]; 
                    match ($question_data['question_type']) {
                        'multiple_choice' => $question_type_service->storeMultipleChoice($question, $question_type_data),
                        'true_or_false' => $question_type_service->storeTrueOrFalse($question, $question_type_data),
                        'identification' => $question_type_service->storeIdentification($question, $question_type_data),
                        'ranking' => $question_type_service->storeRanking($question, $question_type_data),
                        'matching' => $question_type_service->storeMatching($question, $question_type_data),
                        default => throw new \InvalidArgumentException("Unknown question type: {$question_data['question_type']}"),
                    };
                }
            });
        }
}
