<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Exercise Types Table
        Schema::create('exercise_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->string('component_name'); // React component to render this type
            $table->timestamps();
        });

        // Exercises Table
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_type_id')->constrained('exercise_types');
            $table->integer('order')->unsigned();
            $table->text('prompt');
            $table->json('content'); // Structured content based on type
            $table->json('correct_answer');
            $table->json('distractors')->nullable(); // Wrong answers for multiple choice
            $table->integer('xp_reward')->default(10);
            $table->timestamps();

            $table->unique(['lesson_id', 'order']);
        });

        // Seed exercise types
        DB::table('exercise_types')->insert([
            [
                'name' => 'multiple_choice',
                'description' => 'Choose the correct answer from options',
                'component_name' => 'MultipleChoiceExercise',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'translate',
                'description' => 'Translate the given text',
                'component_name' => 'TranslationExercise',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'word_bank',
                'description' => 'Construct sentence from given words',
                'component_name' => 'WordBankExercise',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'fill_in_blank',
                'description' => 'Fill in missing words in a sentence',
                'component_name' => 'FillInBlankExercise',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'match_pairs',
                'description' => 'Match corresponding pairs',
                'component_name' => 'MatchPairsExercise',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'speak',
                'description' => 'Speak the given phrase',
                'component_name' => 'SpeakingExercise',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'listen_type',
                'description' => 'Type what you hear',
                'component_name' => 'ListeningExercise',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // User Exercise Progress
        Schema::create('user_exercise_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->boolean('completed')->default(false);
            $table->integer('attempts')->default(0);
            $table->boolean('correct')->nullable();
            $table->text('user_answer')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exercise_id']);
        });

        // Add hints table for exercises
        Schema::create('exercise_hints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exercise_id')->constrained()->onDelete('cascade');
            $table->text('hint');
            $table->integer('order')->unsigned();
            $table->integer('xp_penalty')->default(5); // XP deduction for using hint
            $table->timestamps();

            $table->unique(['exercise_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_hints');
        Schema::dropIfExists('user_exercise_progress');
        Schema::dropIfExists('exercises');
        Schema::dropIfExists('exercise_types');
    }
};