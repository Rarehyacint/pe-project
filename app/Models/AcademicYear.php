<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AcademicYear extends Model
{
    protected $fillable = [
        'year_label',
        'start_date',
        'end_date',
        'is_locked',
    ];
    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'academic_year_interval' => 'string',
    ];

    protected static function boot()
    {
        parent::boot();

        static::updating(function (AcademicYear $year) {
            
            if ($year->getOriginal('is_locked')) {
                return false;
            }

            if ($year->isCurrent()) {
                
                if ($year->isDirty('start_date')) {
                    return false;
                }
            }
            
        });

        static::deleting(function (AcademicYear $year) {
            
            if ($year->is_locked) {
                return false;
            }

            if ($year->isCurrent()) {
                return false;
            }

        });
    }

    public function exams(){
        return $this->hasMany(Exam::class, 'academic_year_id');
    }
    
    public function scopeCurrent($query){
        return $query->where('start_date', '<=',  today())
                     ->where('end_date', '>=',  today())
                     ->where('is_locked', false);
    }

    public static function getCurrentAndLockPassed(): ?self
    {
        self::lockPassedYears(); 
        return static::where('start_date', '<=', today())
                     ->where('end_date', '>=', today())
                     ->first();
    }
    protected static function lockPassedYears(): int
    {
        $today = Carbon::today()->toDateString();

        $query = static::where('is_locked', false)
                       ->where('end_date', '<', $today);

        if ($query->exists()) {
            return $query->update(['is_locked' => true]);
        }
        return 0;
    }
    public static function current(){
        return self::getCurrentAndLockPassed();
    }

    public function isCurrent(): bool
    {
        $today = today();
        return $this->start_date->lte($today) && $this->end_date->gte($today);
    }
    public static function hasCurrent(): bool
    {
        return !is_null(self::current()); 
    }
     protected function academicYearInterval(): Attribute
    {
        return Attribute::get(function ($value, $attributes) {
            $startYear = Carbon::parse($attributes['start_date'])->year;
            $endYear = Carbon::parse($attributes['end_date'])->year;
            return "{$startYear}-{$endYear}";
        });
    }


}
