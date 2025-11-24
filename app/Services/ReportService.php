<?php

namespace App\Services;
use App\Models\Course;
use App\Models\Exam;
use App\Models\StudentPaper;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Question;
use App\Models\User;
use DB;
class ReportService
{
    public static function ProcessStudentPerformanceJob(StudentPaper $studentPaper){
        $studentPaper->load([
                'exam', 
                'examRecord', 
                'user.courses', // Eager loading the student and their courses
                'studentAnswers.question.topic.subject' // Eager loading the rest of the chain
            ]);
        $exam_id = $studentPaper->exam->id;
        $exam_record = $studentPaper->examRecord;
        $student = $studentPaper->user;
        $student_course = $student->courses->first();

        $student_performance_data = []; 

        foreach ($studentPaper->studentAnswers as $answer) {
            $points_obtained = $answer->points; 
            $question = $answer->question;
            $question_level = $question->questionLevel()->first()->name ?? 'none';
            $topic = $question->topic;
            $subject = $topic->subject;
            
            $student_performance_data[] = [
                'exam_id' => $exam_id,
                'student_paper_id' => $studentPaper->id,
                'attempt' => $exam_record->attempt,
                'user_id' => $student->id,
                'course_id' => $student_course->id,
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'question_id' => $question->id,
                'question_name' => $question->name,
                'question_type' => $question->question_type,
                'question_level' => $question_level,
                'question_points' => $question->total_points,
                'points_obtained' => $points_obtained,
                'created_at' => now(), 
                'updated_at' => now(), 
            ];
        }

        if (!empty($student_performance_data)) {
            DB::table('student_performances')->insert($student_performance_data);
        }
    }
}
