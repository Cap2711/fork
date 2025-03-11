<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->integer('order');
            $table->timestamps();

            // Add index for ordering
            $table->index(['lesson_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};