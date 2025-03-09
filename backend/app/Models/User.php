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

    // Progress Tracking
    public function unitProgress(): HasMany
    {
        return $this->hasMany(UserUnitProgress::class);
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    // Content Completion Tracking
    public function completedVocabulary(): BelongsToMany
    {
        return $this->belongsToMany(VocabularyWord::class, 'user_vocabulary')
            ->withPivot(['lesson_id', 'completed_at'])
            ->withTimestamps();
    }

    public function completedGrammar(): BelongsToMany
    {
        return $this->belongsToMany(GrammarExercise::class, 'user_grammar')
            ->withPivot(['lesson_id', 'completed_at'])
            ->withTimestamps();
    }

    public function completedReading(): BelongsToMany
    {
        return $this->belongsToMany(ReadingPassage::class, 'user_reading')
            ->withPivot(['lesson_id', 'completed_at'])
            ->withTimestamps();
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
        $completedLessons = $this->lessonProgress()
            ->where('completed', true)
            ->count();

        $totalUnits = Unit::count();
        $completedUnits = Unit::whereHas('userProgress', function ($query) {
            $query->where('user_id', $this->id)
                ->where('level', '>', 0);
        })->count();

        return [
            'completed_lessons' => $completedLessons,
            'total_points' => $this->total_points,
            'current_streak' => $this->current_streak,
            'longest_streak' => $this->longest_streak,
            'completed_units' => $completedUnits,
            'total_units' => $totalUnits,
            'vocabulary_mastered' => $this->completedVocabulary()->count(),
            'grammar_mastered' => $this->completedGrammar()->count(),
            'reading_completed' => $this->completedReading()->count(),
        ];
    }

    // Admin Role Check
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}