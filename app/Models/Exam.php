<?php

namespace App\Models;

use App\TracksUserActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Exam extends Model
{
    use HasFactory, Notifiable, TracksUserActivity;

    protected $fillable = [
        'name',
        'academic_year_id',
        'course_id',
        'access_code',
        'max_score',
        'duration',
        'passing_score',
        'retakes',
        'examination_date',
        'expiration_date',
        'is_published',
        'applied_algorithm'
    ];
    protected $casts = [
        'examination_date' => 'datetime',
        'expiration_date' => 'datetime'
    ];

    public function academicYear(){
        return $this->belongsTo(AcademicYear::class);
    }

    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }

    public function reports() {
        return $this->hasMany(Report::class);
    }
    

    public function accessCodes() {
        return $this->hasMany(ExamAccessCode::class);
    }

    public function studentPapers() {
        return $this->hasMany(StudentPaper::class);
    }

    public function questions() {
        return $this->belongsToMany(Question::class)->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'exams_enrolled_users')
                    ->withPivot('access_code')
                    ->withTimestamps();
    }

    public function takers()
    {
        return $this->hasManyThrough(
            \App\Models\User::class,         
            \App\Models\StudentPaper::class, 
            'exam_id',                       
            'id',                           
            'id',                            
            'user_id'                        
        )
        ->where('student_papers.status', 'completed') 
        ->distinct()->get();
    }



    public function createdBy(){
        return $this->belongsTo(User::class, 'created_by');
        }
    
    public function updatedBy(){
        return $this->belongsTo(User::class, 'updated_by');
    }
}
