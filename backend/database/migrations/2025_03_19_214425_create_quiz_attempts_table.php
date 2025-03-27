<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('answers');
            $table->decimal('score', 5, 2);
            $table->boolean('passed');
            $table->integer('time_taken_seconds')->nullable();
            $table->json('question_results')->nullable(); // Store per-question results
            $table->timestamps();

            $table->index(['quiz_id', 'user_id', 'created_at']);
            $table->index(['quiz_id', 'passed']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};