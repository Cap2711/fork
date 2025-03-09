<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUnitProgress extends Model
{
    protected $fillable = [
        'user_id',
        'unit_id',
        'level',
    ];

    protected $casts = [
        'level' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function getLessonProgress(): array
    {
        $lessonProgress = $this->unit->userLessonProgress()
            ->where('user_id', $this->user_id)
            ->get();

        $totalLessons = $this->unit->lessons()->count();
        $completedLessons = $lessonProgress->where('completed', true)->count();
        $averageScore = $lessonProgress->avg('score') ?? 0;

        return [
            'total_lessons' => $totalLessons,
            'completed_lessons' => $completedLessons,
            'progress_percentage' => $totalLessons > 0 
                ? round(($completedLessons / $totalLessons) * 100)
                : 0,
            'average_score' => round($averageScore),
        ];
    }

    public function shouldLevelUp(): bool
    {
        // Check if all lessons are completed with high scores
        $progress = $this->getLessonProgress();
        return $progress['completed_lessons'] === $progress['total_lessons'] &&
            $progress['average_score'] >= 90;
    }

    public function getCrownColor(): string
    {
        if ($this->level >= 5) {
            return 'legendary'; // Purple crown
        }
        if ($this->level >= 4) {
            return 'gold';
        }
        if ($this->level >= 3) {
            return 'silver';
        }
        if ($this->level >= 2) {
            return 'bronze';
        }
        if ($this->level >= 1) {
            return 'basic'; // Red crown
        }
        return 'none';
    }

    public function isEligibleForPractice(): bool
    {
        // Suggest practice if it's been a while or scores are dropping
        if ($this->level === 0) {
            return false;
        }

        $lastActivity = $this->unit->userLessonProgress()
            ->where('user_id', $this->user_id)
            ->where('completed', true)
            ->latest('completed_at')
            ->first();

        if (!$lastActivity) {
            return false;
        }

        $daysSinceLastActivity = $lastActivity->completed_at->diffInDays(now());
        $recentScores = $this->unit->userLessonProgress()
            ->where('user_id', $this->user_id)
            ->where('completed', true)
            ->latest('completed_at')
            ->limit(3)
            ->pluck('score');

        // Suggest practice if:
        // 1. It's been more than 2 weeks since last activity, or
        // 2. Recent scores are trending downward
        return $daysSinceLastActivity > 14 || 
            ($recentScores->count() >= 3 && $recentScores->avg() < 80);
    }
}