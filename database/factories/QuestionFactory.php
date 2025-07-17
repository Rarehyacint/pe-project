<?php

namespace Database\Factories;

use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $questionType = $this->faker->randomElement(['multiple_choice', 'true_or_false', 'identification', 'ranking', 'matching']);
        $questionWords = ['What', 'How', 'Why', 'When', 'Where', 'Who'];

        // Randomly choose a question word
        $questionWord = $this->faker->randomElement($questionWords);

        return [
            'topic_id' => Topic::inRandomOrder()->first()->id,
            'question_type' => $questionType,
            'name' => $questionWord . ' ' . fake()->realText(50) . '?',
            'points' => $this->faker->numberBetween(1, 5),
            'created_by' => User::find(1),
            'updated_by' => User::find(1)
        ];
    }
}
