<?php

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
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->foreignId('section_id')->nullable()->constrained()->onDelete('cascade');
            
            // Quiz details
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('practice'); // practice or assessment
            
            // Configuration
            $table->integer('passing_score')->default(70);
            $table->integer('time_limit')->nullable(); // In minutes, null for no limit
            $table->string('difficulty_level')->default('beginner');
            $table->boolean('is_published')->default(false);
            
            // Practice settings
            $table->boolean('allow_retry')->default(true);
            $table->boolean('show_feedback')->default(true);
            $table->integer('xp_reward')->default(20);
            
            // Assessment settings
            $table->boolean('requires_previous')->default(true);
            $table->boolean('show_solutions_after')->default(false);
            $table->integer('min_correct_required')->nullable();
            
            // Organization
            $table->integer('order')->default(0);
            $table->json('metadata')->nullable(); // Additional quiz settings
            
            $table->timestamps();
        });

        // Create quiz_questions table
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            
            // Question content
            $table->string('type'); // multiple-choice, fill-in-blank, etc.
            $table->json('content'); // Question-specific content structure
            $table->string('correct_answer');
            $table->json('options')->nullable(); // For multiple choice, matching, etc.
            $table->text('explanation')->nullable();
            
            // Configuration
            $table->integer('points')->default(1);
            $table->string('difficulty_level')->default('normal');
            $table->integer('time_limit')->nullable(); // In seconds, for timed questions
            $table->boolean('is_optional')->default(false);
            $table->integer('order')->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};