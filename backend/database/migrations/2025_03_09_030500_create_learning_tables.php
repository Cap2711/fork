<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Units table
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            $table->integer('order')->unique();
            $table->enum('difficulty', ['beginner', 'intermediate', 'advanced']);
            $table->boolean('is_locked')->default(true);
            $table->timestamps();
        });

        // Lessons table
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('description');
            $table->integer('order')->unsigned();
            $table->integer('xp_reward')->default(10);
            $table->timestamps();

            // Ensure lessons within a unit have unique order
            $table->unique(['unit_id', 'order']);
        });

        // User Unit Progress table
        Schema::create('user_unit_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('unit_id')->constrained()->onDelete('cascade');
            $table->integer('level')->default(0);
            $table->timestamps();

            // Each user can have one progress record per unit
            $table->unique(['user_id', 'unit_id']);
        });

        // User Lesson Progress table
        Schema::create('user_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->boolean('completed')->default(false);
            $table->integer('score')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Each user can have one progress record per lesson
            $table->unique(['user_id', 'lesson_id']);
        });

        // User Streaks table
        Schema::create('user_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('current_streak')->default(0);
            $table->integer('longest_streak')->default(0);
            $table->date('last_activity_date')->nullable();
            $table->timestamps();

            // Each user can have only one streak record
            $table->unique('user_id');
        });

        // XP History table
        Schema::create('xp_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('amount');
            $table->string('source');
            $table->foreignId('lesson_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_history');
        Schema::dropIfExists('user_streaks');
        Schema::dropIfExists('user_lesson_progress');
        Schema::dropIfExists('user_unit_progress');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('units');
    }
};