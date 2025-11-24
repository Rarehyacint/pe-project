<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodingQuestion extends Model
{
    protected $fillable = [
        'question_id',
        'instruction',
        'is_syntax_code_only',
        'enable_compilation',
        'syntax_points',
        'runtime_points',
        'test_case_points',
        'syntax_points_deduction_per_error',
        'runtime_points_deduction_per_error',
        'test_case_points_deduction_per_error'
    ];

    public function question(){
        return $this->belongsTo(Question::class);
    }
    public function codingQuestionLanguages(){
        return $this->hasMany(CodingQuestionLanguage::class);
    }

    public function getSpecificLanguage(string $language){
        return $this->codingQuestionLanguages->where('language', $language)->first();
    }
}
