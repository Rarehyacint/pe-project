<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use App\TracksUserActivity;

class Question extends Model
{
    use HasFactory, Notifiable, TracksUserActivity;

    protected $fillable = [
        'question_type',
        'topic_id',
        'name',
        'points'
    ];

    protected $casts = [
        'question_type' => QuestionType::class,
    ];

    public function topic(){
        return $this->belongsTo(Topic::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exams() {
        return $this->belongsToMany(Exam::class)->withTimestamps();
    }

    public function multipleChoiceQuestions(){
        return $this->hasMany(MultipleChoiceQuestion::class);
    }
    public function trueOrFalseQuestion(){
        return $this->hasOne(TrueOrFalseQuestion::class);
    }
    public function identificationQuestion(){
        return $this->hasOne(IdentificationQuestion::class);
    }
    public function rankingQuestions(){
        return $this->hasMany(RankingQuestion::class);
    }
    public function matchingQuestions(){
        return $this->hasMany(MatchingQuestion::class);
    }

    public function getTypeModel()
    {                

        switch ($this->question_type) {
            case QuestionType::MultipleChoice:
                $this->load('multipleChoiceQuestions');
                return $this->multipleChoiceQuestions;
                
            case QuestionType::TrueOrFalse:
                $this->load('trueOrFalseQuestion');
                return $this->trueOrFalseQuestion;
                
            case QuestionType::Identification:
                $this->load('identificationQuestion');
                return $this->identificationQuestion;
                
            case QuestionType::Ranking:
                $this->load('rankingQuestions');
                return $this->rankingQuestions;
                
            case QuestionType::Matching:
                $this->load('matchingQuestions');
                return $this->matchingQuestions;
        }
    }
    

}
