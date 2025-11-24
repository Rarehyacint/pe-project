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

    public function users(){
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class);
    }

    public function exams()
    {
        return $this->belongsToMany(Exam::class);
    }
    
    public function createdBy(){
    return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(){
        return $this->belongsTo(User::class, 'updated_by');
    }

}
