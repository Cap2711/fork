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
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');
            $table->foreignId('section_id')->nullable()->constrained()->onDelete('cascade');
            
            // Exercise details
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type'); // vocabulary, grammar, pronunciation, etc.
            $table->json('content'); // Exercise-specific content structure
            $table->json('metadata')->nullable(); // Additional exercise settings
            
            // Configuration
            $table->integer('time_limit')->nullable(); // In seconds, null for no limit
            $table->integer('max_attempts')->nullable(); // null for unlimited
            $table->boolean('show_feedback')->default(true);
            $table->boolean('show_hints')->default(true);
            $table->integer('xp_reward')->default(5);
            
            // Organization
            $table->string('difficulty_level')->default('beginner');
            $table->integer('order')->default(0);
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->boolean('is_published')->default(false);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};