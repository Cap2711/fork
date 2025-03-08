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
        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->morphs('activity'); // For vocabulary_words, grammar_exercises, or reading_passages
            $table->boolean('completed')->default(false);
            $table->integer('score')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('answers')->nullable(); // Store user's answers
            $table->boolean('is_correct')->nullable();
            $table->integer('time_spent_seconds')->default(0);
            $table->text('feedback')->nullable();
            $table->integer('points_earned')->default(0);
            // Add indexes for better query performance
            $table->index(['user_id', 'activity_type', 'activity_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_progress');
    }
};
