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
        Schema::create('vocabulary_words', function (Blueprint $table) {
            $table->id();
            $table->string('word');
            $table->text('definition');
            $table->text('example_sentence');
            $table->string('part_of_speech');
            $table->enum('difficulty_level', ['easy', 'medium', 'hard']);
            $table->enum('grade_level', ['K', '1', '2', '3', '4', '5', '6', '7', '8', '9']);
            $table->string('image_url')->nullable();
            $table->string('audio_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vocabulary_words');
    }
};
