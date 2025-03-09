<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLessonProgress extends Model
{
    protected $fillable = [
        'user_id',
        'lesson_id',
        'completed',
        'score',
        'completed_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'score' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function getPerfectScore(): bool
    {
        return $this->score === 100;
    }

    public function getMasteryLevel(): string
    {
        if (!$this->completed) {
            return 'not_started';
        }

        if ($this->score >= 95) {
            return 'mastered';
        }

        if ($this->score >= 80) {
            return 'proficient';
        }

        if ($this->score >= 60) {
            return 'learning';
        }

        return 'needs_practice';
    }

    public function shouldReview(): bool
    {
        if (!$this->completed) {
            return false;
        }

        // Suggest review if score is low or it's been a while since completion
        return $this->score < 80 || 
            $this->completed_at->diffInDays(now()) > 30;
    }
}