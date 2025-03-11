<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')
                ->constrained()
                ->onDelete('cascade');
            $table->enum('type', ['multiple_choice', 'fill_blank', 'matching', 'writing', 'speaking']);
            $table->json('content');
            $table->json('answers');
            $table->integer('order');
            $table->timestamps();

            // Add index for ordering and type queries
            $table->index(['section_id', 'order']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};