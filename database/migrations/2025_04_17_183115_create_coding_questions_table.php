<?php

use App\Models\Question;
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
        Schema::create('coding_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Question::class, 'question_id')->constrained()->cascadeOnDelete();
            $table->text('instruction');
            $table->boolean('is_syntax_code_only')->default(false);
            $table->boolean('enable_compilation')->default(false);
            $table->unsignedTinyInteger('syntax_points');
            $table->unsignedTinyInteger('runtime_points');
            $table->unsignedTinyInteger('test_case_points');
            $table->unsignedTinyInteger('syntax_points_deduction_per_error');
            $table->unsignedTinyInteger('runtime_points_deduction_per_error');
            $table->unsignedTinyInteger('test_case_points_deduction_per_error');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coding_questions');
    }
};
