<?php

use App\Models\Exam;
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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Exam::class, 'exam_id')->constrained();
            $table->unsignedTinyInteger('course_count');
            $table->unsignedSmallInteger('subject_count');
            $table->unsignedSmallInteger('topic_count');
            $table->unsignedSmallInteger('question_count');
            $table->unsignedSmallInteger('student_count');
            $table->jsonb('report_data');
            $table->jsonb('raw_report_data');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
        Schema::dropIfExists('reports');
    }
};
