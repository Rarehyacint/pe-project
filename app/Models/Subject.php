<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use App\TracksUserActivity;

class Subject extends Model
{
    use HasFactory, Notifiable, TracksUserActivity;

    protected $fillable = [
        'name',
        'code',
        'year_level',
        'course_id'
    ];

    public function courses()
    {
        return $this->belongsToMany(Course::class);
    }

    public function topics(){
        return $this->hasMany(Topic::class, 'subject_id');
    }

    public function createdBy(){
    return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(){
        return $this->belongsTo(User::class, 'updated_by');
    }
}
