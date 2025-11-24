<?php

namespace App\Models;

use App\Jobs\ProcessStudentPerformanceJob;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Model;

class StudentPaper extends Model
{
    protected $fillable = [
        'exam_id',
        'user_id',
        'question_count',
        'questions_order',
        'current_position',
        'status',
        'last_seen_at',
        'submitted_at',
        'expired_at'
    ];
    protected $casts = [
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'expired_at' => 'datetime',
    ];

    public function exam(){
        return $this->belongsTo(Exam::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }

    public function examRecord(){
        return $this->hasOne(ExamRecord::class);
    }

    public function studentAnswers() {
        return $this->hasMany(StudentAnswer::class);
    }

    public function getRemainingDuration(){
        if ($this->expired_at != null){
            return now()->diffInSeconds($this->expired_at, false);
        } else {
            return null;
        }
    }

    public function isExpired(){
        if ($this->expired_at != null){
            return now() > $this->expired_at;
        } else {
            return false;
        }
    }
    public function isSubmitted(){
        return $this->submitted_at != null;
    }

    protected static function booted()
    {
        self::updated(static function (StudentPaper $studentPaper) {
            
            if ($studentPaper->status == 'completed') {
                ProcessStudentPerformanceJob::dispatch($studentPaper);
            }
        });
    }
}
