<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('title');
            $table->text('description');
            $table->integer('order');
            $table->timestamps();

            // Add index for ordering
            $table->index(['unit_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};