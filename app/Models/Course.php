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

    public function subjects(){
        return $this->hasMany(Subject::class);
    }

    public function exams(){
        return $this->hasMany(Exam::class);
    }




}
