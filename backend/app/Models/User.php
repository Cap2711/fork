<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'google_id',
        'avatar',
        'current_streak',
        'longest_streak',
        'last_activity_date',
        'total_points',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'google_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
        'total_points' => 'integer',
        'last_activity_date' => 'datetime',
    ];

    /**
     * Get all progress records for the user.
     */
    public function progress(): HasMany
    {
        return $this->hasMany(UserProgress::class);
    }

    /**
     * Helper methods for checking progress
     */
    public function getProgressFor($model): ?UserProgress
    {
        return $this->progress()
            ->where('trackable_type', get_class($model))
            ->where('trackable_id', $model->id)
            ->first();
    }

    // Streak Management
    public function updateStreak(): void
    {
        $today = now()->startOfDay();
        $lastActivity = $this->last_activity_date?->startOfDay();

        // If this is the first activity or it's been more than a day
        if (!$lastActivity || $lastActivity->diffInDays($today) > 1) {
            $this->current_streak = 1;
        } 
        // If the last activity was yesterday
        elseif ($lastActivity->diffInDays($today) === 1) {
            $this->current_streak++;
        }
        // If it's the same day, don't update the streak

        // Update longest streak if current is higher
        if ($this->current_streak > $this->longest_streak) {
            $this->longest_streak = $this->current_streak;
        }

        $this->last_activity_date = $today;
        $this->save();
    }

    // XP Management
    public function awardXp(int $amount, string $source, ?int $lessonId = null): void
    {
        $this->total_points += $amount;
        $this->save();

        // Record XP history
        $this->xpHistory()->create([
            'amount' => $amount,
            'source' => $source,
            'lesson_id' => $lessonId,
        ]);

        // Update streak
        $this->updateStreak();
    }

    public function xpHistory(): HasMany
    {
        return $this->hasMany(XpHistory::class);
    }

    // Progress Summary
    public function getProgressSummary(): array
    {
        $progress = $this->progress();
        
        $completedLessons = $progress
            ->where('trackable_type', Lesson::class)
            ->where('status', UserProgress::STATUS_COMPLETED)
            ->count();

        $totalUnits = Unit::count();
        $completedUnits = $progress
            ->where('trackable_type', Unit::class)
            ->where('status', UserProgress::STATUS_COMPLETED)
            ->count();

        $vocabularyMastered = $progress
            ->where('trackable_type', VocabularyItem::class)
            ->where('status', UserProgress::STATUS_COMPLETED)
            ->count();

        $exercisesCompleted = $progress
            ->where('trackable_type', Exercise::class)
            ->where('status', UserProgress::STATUS_COMPLETED)
            ->count();

        return [
            'completed_lessons' => $completedLessons,
            'total_points' => $this->total_points,
            'current_streak' => $this->current_streak,
            'longest_streak' => $this->longest_streak,
            'completed_units' => $completedUnits,
            'total_units' => $totalUnits,
            'vocabulary_mastered' => $vocabularyMastered,
            'exercises_completed' => $exercisesCompleted,
        ];
    }

    // Admin Role Check
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}