<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('title');
            $table->integer('passing_score')
                ->default(70)
                ->comment('Minimum percentage required to pass');
            $table->timestamps();

            // Add index for unit queries
            $table->index('unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};