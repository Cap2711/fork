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
        // Core language management
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5);  // ISO code (e.g., 'en', 'ja', 'es')
            $table->string('name');     // Display name
            $table->string('native_name'); // Name in the language itself
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('code');
        });

        Schema::create('language_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_language_id')->constrained('languages');
            $table->foreignId('target_language_id')->constrained('languages');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['source_language_id', 'target_language_id']);
        });

        // Word management
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained();
            $table->string('text');
            $table->string('pronunciation_key')->nullable(); // IPA or similar
            $table->string('part_of_speech')->nullable();
            $table->json('metadata')->nullable(); // Additional word properties
            $table->timestamps();

            $table->unique(['language_id', 'text', 'part_of_speech']);
        });

        Schema::create('word_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('word_id')->constrained()->onDelete('cascade');
            $table->foreignId('language_id')->constrained(); // Target language
            $table->string('text');
            $table->string('pronunciation_key')->nullable();
            $table->text('context_notes')->nullable();
            $table->json('usage_examples')->nullable();
            $table->integer('translation_order')->default(1); // For multiple meanings
            $table->timestamps();

            $table->index(['word_id', 'language_id', 'translation_order']);
        });

        // Sentence management
        Schema::create('sentences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained();
            $table->text('text');
            $table->string('pronunciation_key')->nullable();
            $table->json('metadata')->nullable(); // Difficulty level, tags, etc.
            $table->timestamps();
        });

        Schema::create('sentence_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sentence_id')->constrained()->onDelete('cascade');
            $table->foreignId('language_id')->constrained(); // Target language
            $table->text('text');
            $table->string('pronunciation_key')->nullable();
            $table->text('context_notes')->nullable();
            $table->timestamps();

            $table->unique(['sentence_id', 'language_id']);
        });

        // Word-sentence relationships with timing
        Schema::create('sentence_words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sentence_id')->constrained()->onDelete('cascade');
            $table->foreignId('word_id')->constrained();
            $table->integer('position'); // Word order in sentence
            $table->float('start_time')->nullable(); // Start time in audio (seconds)
            $table->float('end_time')->nullable();   // End time in audio (seconds)
            $table->json('metadata')->nullable(); // Any additional timing/display info
            $table->timestamps();

            $table->unique(['sentence_id', 'position']);
            $table->index(['sentence_id', 'word_id']);
        });

        // Usage examples
        Schema::create('usage_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('word_id')->constrained()->onDelete('cascade');
            $table->foreignId('sentence_id')->constrained();
            $table->string('type'); // common usage, idiom, formal, casual, etc.
            $table->integer('difficulty_level');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['word_id', 'type', 'difficulty_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_examples');
        Schema::dropIfExists('sentence_words');
        Schema::dropIfExists('sentence_translations');
        Schema::dropIfExists('sentences');
        Schema::dropIfExists('word_translations');
        Schema::dropIfExists('words');
        Schema::dropIfExists('language_pairs');
        Schema::dropIfExists('languages');
    }
};