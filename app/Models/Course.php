<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use App\TracksUserActivity;

class Course extends Model
{
    use HasFactory, Notifiable, TracksUserActivity;

    protected $fillable = [
        'name',
        'abbreviation'
    ];

    protected $appends = [
        'subjects_count',
        'topics_count',
        'questions_count'
    ];

    public function users(){
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function subjects(){
        return $this->hasMany(Subject::class);
    }

    public function exams(){
        return $this->hasMany(Exam::class);
    }
    public function createdBy(){
    return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(){
        return $this->belongsTo(User::class, 'updated_by');
    }



}
