<?php

namespace App\Enums;

enum AnswerType: string
{
    case MultipleChoice = 'multiple_choice';
    case TrueOrFalse = 'true_or_false';
    case Identification = 'identification';
    case Ranking = 'ranking';
    case Matching = 'matching';
    case Coding = 'coding';

    public function getName(): string {
        return match($this) {
            self::MultipleChoice => 'Multiple Choice',
            self::TrueOrFalse => 'True/False',
            self::Identification => 'Identification',
            self::Ranking => 'Ranking',
            self::Matching => 'Matching',
            self::Coding => 'Coding',
        };
    }
}
