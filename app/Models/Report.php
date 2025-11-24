<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'exam_id',
        'course_count',
        'subject_count',
        'topic_count',
        'question_count',
        'student_count',
        'report_data',
        'raw_report_data'
    ];
    protected $casts = [
        'report_data' => 'array',
        'raw_report_data' => 'array',
    ];

    public function exam(){
        return $this->belongsTo(Exam::class);
    }
}
