<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MultipleChoiceQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'choice_key',
        'item',
        'is_correct'
    ];

    public function question(){
        return $this->belongsTo(Question::class);
    }
}
