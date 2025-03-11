<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XpHistory extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'source',
        'lesson_id',
        'metadata'
    ];

    protected $casts = [
        'amount' => 'integer',
        'metadata' => 'array'
    ];

    /**
     * The possible sources of XP.
     */
    const SOURCE_LESSON_COMPLETION = 'lesson_completion';
    const SOURCE_EXERCISE_COMPLETION = 'exercise_completion';
    const SOURCE_QUIZ_COMPLETION = 'quiz_completion';
    const SOURCE_STREAK_BONUS = 'streak_bonus';
    const SOURCE_ACHIEVEMENT = 'achievement';

    /**
     * Get the user that owns this XP record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the lesson this XP was earned from, if applicable.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get formatted amount with + sign.
     */
    public function getFormattedAmountAttribute(): string
    {
        return sprintf('+%d XP', $this->amount);
    }

    /**
     * Get human-readable source description.
     */
    public function getSourceDescriptionAttribute(): string
    {
        return match ($this->source) {
            self::SOURCE_LESSON_COMPLETION => 'Lesson Completed',
            self::SOURCE_EXERCISE_COMPLETION => 'Exercise Completed',
            self::SOURCE_QUIZ_COMPLETION => 'Quiz Completed',
            self::SOURCE_STREAK_BONUS => 'Streak Bonus',
            self::SOURCE_ACHIEVEMENT => 'Achievement Unlocked',
            default => ucfirst(str_replace('_', ' ', $this->source))
        };
    }
}