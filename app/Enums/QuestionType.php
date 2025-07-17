<?php

namespace App\Enums;

enum QuestionType: string
{
    case MultipleChoice = 'multiple_choice';
    case TrueOrFalse = 'true_or_false';
    case Identification = 'identification';
    case Ranking = 'ranking';
    case Matching = 'matching';

    public function getName(): string {
        return match($this) {
            self::MultipleChoice => 'Multiple Choice Question',
            self::TrueOrFalse => 'True/False Question',
            self::Identification => 'Identification Question',
            self::Ranking => 'Ranking Question',
            self::Matching => 'Matching Question',
        };
    }
}
