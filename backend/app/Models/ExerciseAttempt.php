<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseAttempt extends Model
{
    protected $fillable = [
        'exercise_id',
        'user_id',
        'is_correct',
        'user_answer',
        'time_taken_seconds'
    ];

    protected $casts = [
        'user_answer' => 'array',
        'is_correct' => 'boolean',
        'time_taken_seconds' => 'integer'
    ];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}