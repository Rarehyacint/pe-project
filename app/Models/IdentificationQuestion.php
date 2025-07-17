<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentificationQuestion extends Model
{
    protected $fillable = [
        'question_id',
        'solution'
    ];

    public function question(){
        return $this->belongsTo(Question::class);
    }
}
