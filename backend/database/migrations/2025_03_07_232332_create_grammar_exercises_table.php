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
        Schema::create('grammar_exercises', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('type', ['multiple_choice', 'fill_in_blanks', 'sentence_correction', 'matching']);
            $table->text('question');
            $table->json('options')->nullable(); // For multiple choice or matching
            $table->text('correct_answer');
            $table->text('explanation');
            $table->enum('difficulty_level', ['easy', 'medium', 'hard']);
            $table->enum('grade_level', ['K', '1', '2', '3', '4', '5', '6', '7', '8', '9']);
            $table->string('category'); // e.g., 'nouns', 'verbs', 'adjectives'
            $table->integer('points')->default(10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grammar_exercises');
    }
};
