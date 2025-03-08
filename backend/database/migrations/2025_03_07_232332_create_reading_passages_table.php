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
        Schema::create('reading_passages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('genre'); // fiction, non-fiction, poetry, etc.
            $table->enum('grade_level', ['K', '1', '2', '3', '4', '5', '6', '7', '8', '9']);
            $table->integer('reading_time_minutes');
            $table->integer('word_count');
            $table->json('vocabulary_words'); // Key words from the passage
            $table->json('comprehension_questions'); // Array of questions and answers
            $table->enum('difficulty_level', ['easy', 'medium', 'hard']);
            $table->string('image_url')->nullable();
            $table->integer('points')->default(20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reading_passages');
    }
};
