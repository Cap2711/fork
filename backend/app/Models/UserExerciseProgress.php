<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserExerciseProgress extends Model
{
    protected $fillable = [
        'user_id',
        'exercise_id',
        'completed',
        'attempts',
        'correct',
        'user_answer',
        'completed_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'attempts' => 'integer',
        'correct' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function getMasteryLevel(): string
    {
        if (!$this->completed) {
            return 'not_started';
        }

        if (!$this->correct) {
            return 'needs_practice';
        }

        if ($this->attempts === 1) {
            return 'perfect'; // First try success
        }

        if ($this->attempts <= 3) {
            return 'good'; // Success within 3 attempts
        }

        return 'learned'; // Eventually succeeded
    }

    public function needsReview(): bool
    {
        if (!$this->completed) {
            return false;
        }

        // Review is needed if:
        // 1. User took many attempts to complete
        // 2. It's been a while since last completion
        // 3. User got it wrong after previously getting it right
        return $this->attempts > 3 ||
            $this->completed_at->diffInDays(now()) > 7 ||
            (!$this->correct && $this->completed);
    }

    public function getTimeToComplete(): ?int
    {
        if (!$this->completed || !$this->completed_at) {
            return null;
        }

        // Return time to complete in seconds
        return $this->created_at->diffInSeconds($this->completed_at);
    }

    public function getPerformanceScore(): int
    {
        if (!$this->completed || !$this->correct) {
            return 0;
        }

        $baseScore = 100;

        // Deduct points for multiple attempts
        $attemptPenalty = ($this->attempts - 1) * 10;
        
        // Deduct points for slow completion (if took more than 30 seconds)
        $timePenalty = 0;
        $timeToComplete = $this->getTimeToComplete();
        if ($timeToComplete > 30) {
            $timePenalty = min(30, floor(($timeToComplete - 30) / 10) * 5);
        }

        return max(0, $baseScore - $attemptPenalty - $timePenalty);
    }

    public function isEligibleForPractice(): bool
    {
        if (!$this->completed) {
            return false;
        }

        // Suggest practice if:
        // 1. Performance score is low
        // 2. It's been a while since completion
        // 3. Previously got it wrong
        return $this->getPerformanceScore() < 70 ||
            $this->completed_at->diffInDays(now()) > 14 ||
            $this->attempts > 3;
    }
}