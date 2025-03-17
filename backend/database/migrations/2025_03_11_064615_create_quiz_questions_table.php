<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};