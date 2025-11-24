<?php

use App\Models\Course;
use App\Models\Exam;
use App\Models\Question;
use App\Models\StudentPaper;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Exam::class, 'exam_id')->constrained();
            $table->foreignIdFor(StudentPaper::class, 'student_paper_id')->constrained();
            $table->unsignedTinyInteger('attempt');
            $table->foreignIdFor(User::class, 'user_id')->constrained();

            $table->foreignIdFor(Course::class, 'course_id')->constrained();
            $table->foreignIdFor(Subject::class, 'subject_id')->constrained();
            $table->foreignIdFor(Topic::class, 'topic_id')->constrained();
            $table->foreignIdFor(Question::class, 'question_id')->constrained();

            $table->string('course_abbreviation');
            $table->string('subject_name');
            $table->string('topic_name');
            $table->text('question_name');
            $table->enum('question_type', [
                'multiple_choice',
                'true_or_false',
                'identification',
                'ranking',
                'matching',
                'coding'
            ]);
            $table->string('question_level');   
            $table->unsignedTinyInteger('question_points');

            $table->boolean('is_answered')->default(false);
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('points_obtained')->default(0);
            $table->timestamp('first_viewed_at')->nullable();
            $table->timestamp('first_answered_at')->nullable(); 
            $table->timestamp('last_answered_at')->nullable(); 

            $table->timestamps();

            $table->index(['attempt']); 
            $table->index(['user_id', 'exam_id']); 
            $table->index(['course_id', 'subject_id', 'topic_id']); 
            $table->index(['question_type']);
            $table->index(['question_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_performances');
    }
};
