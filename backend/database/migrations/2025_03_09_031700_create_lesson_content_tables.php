<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create pivot table for lessons and vocabulary words
        Schema::create('lesson_vocabulary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->foreignId('vocabulary_word_id')->constrained('vocabulary_words')->onDelete('cascade');
            $table->integer('order')->unsigned();
            $table->timestamps();

            // Each word appears once per lesson in a specific order
            $table->unique(['lesson_id', 'vocabulary_word_id']);
            $table->unique(['lesson_id', 'order']);
        });

        // Create pivot table for lessons and grammar exercises
        Schema::create('lesson_grammar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->foreignId('grammar_exercise_id')->constrained('grammar_exercises')->onDelete('cascade');
            $table->integer('order')->unsigned();
            $table->timestamps();

            // Each exercise appears once per lesson in a specific order
            $table->unique(['lesson_id', 'grammar_exercise_id']);
            $table->unique(['lesson_id', 'order']);
        });

        // Create pivot table for lessons and reading passages
        Schema::create('lesson_reading', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->foreignId('reading_passage_id')->constrained('reading_passages')->onDelete('cascade');
            $table->integer('order')->unsigned();
            $table->timestamps();

            // Each passage appears once per lesson in a specific order
            $table->unique(['lesson_id', 'reading_passage_id']);
            $table->unique(['lesson_id', 'order']);
        });

        // Add lesson type column to lessons table
        Schema::table('lessons', function (Blueprint $table) {
            $table->enum('type', ['mixed', 'vocabulary', 'grammar', 'reading'])
                ->default('mixed')
                ->after('description');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_reading');
        Schema::dropIfExists('lesson_grammar');
        Schema::dropIfExists('lesson_vocabulary');

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};