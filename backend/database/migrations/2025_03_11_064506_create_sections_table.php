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
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            
            // Basic section info
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('theory'); // theory, practice, or mini_quiz
            $table->integer('order')->default(0);
            
            // Content and configuration
            $table->json('content')->nullable(); // For theory/example content
            $table->json('practice_config')->nullable(); // For practice exercises config
            
            // Progress requirements
            $table->boolean('requires_previous')->default(true);
            $table->integer('xp_reward')->default(10);
            $table->integer('estimated_time')->default(5); // minutes
            $table->integer('min_correct_required')->nullable();
            $table->boolean('allow_retry')->default(true);
            $table->boolean('show_solution')->default(true);
            
            // Status and metadata
            $table->boolean('is_published')->default(false);
            $table->string('difficulty_level')->default('beginner');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
