<?php

namespace App\Jobs;

use App\Models\StudentPaper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Log;

class ProcessStudentPerformanceJob implements ShouldQueue
{
    use Queueable;
    public StudentPaper $studentPaper;

    /**
     * Create a new job instance.
     */
    public function __construct(StudentPaper $studentPaper)
    {
        // Eloquent models are automatically serialized/deserialized for the queue.
        $this->studentPaper = $studentPaper;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->studentPaper->load([
            'exam', 
            'examRecord', 
            'user.courses', 
            'studentAnswers.question.topic.subject',
        ]);

        $exam_id = $this->studentPaper->exam->id;
        $exam_record = $this->studentPaper->examRecord;
        $student = $this->studentPaper->user;
        
        if (!$exam_record || !$student || $student->courses->isEmpty()) {
            Log::warning("ProcessStudentPerformanceJob failed for StudentPaper ID {$this->studentPaper->id}: Missing related data (ExamRecord, User, or Course).");
            return;
        }

        $student_course = $student->courses->first();
        $student_performance_data = []; 

        foreach ($this->studentPaper->studentAnswers as $answer) {
            $points_obtained = $answer->points; 
            $question = $answer->question;
            $question_level = $question->questionLevel()->first()->name ?? 'none';
            
            $topic = $question->topic;
            $subject = $topic->subject;
            
            if (!$topic || !$subject) {
                 Log::warning("Missing Topic/Subject data for Question ID {$question->id}. Skipping record for StudentPaper ID {$this->studentPaper->id}.");
                 continue; 
            }
            
            $student_performance_data[] = [
                'exam_id' => $exam_id,
                'student_paper_id' => $this->studentPaper->id,
                'attempt' => $exam_record->attempt,
                'user_id' => $student->id,

                'course_id' => $student_course->id,
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'question_id' => $question->id,
                'course_abbreviation' => $student_course->abbreviation,
                'subject_name' => $subject->name,
                'topic_name' => $topic->name,
                'question_name' => $question->name,
                'question_type' => $question->question_type,
                'question_level' => $question_level,
                'question_points' => $question->total_points ?? 0, 

                'is_answered' => $answer->is_answered,
                'is_correct' => $answer->is_correct,
                'points_obtained' => $points_obtained,
                'first_viewed_at' => $answer->first_viewed_at,
                'first_answered_at' => $answer->first_answered_at,
                'last_answered_at' => $answer->last_answered_at,

                'created_at' => now(), 
                'updated_at' => now(), 
            ];
        }

        if (!empty($student_performance_data)) {
            DB::table('student_performances')->insert($student_performance_data);
        }
    }
}
