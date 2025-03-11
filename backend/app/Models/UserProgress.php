<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trackable_id',
        'trackable_type',
        'status',
        'meta_data'
    ];

    protected $casts = [
        'meta_data' => 'json'
    ];

    /**
     * Valid status values
     */
    const STATUS_NOT_STARTED = 'not_started';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * Get the user that owns the progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent trackable model.
     */
    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include completed progress.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope a query to only include in progress items.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    /**
     * Get the progress statistics for a user
     */
    public static function getUserStatistics(int $userId): array
    {
        $stats = [
            'completed_learning_paths' => 0,
            'completed_units' => 0,
            'completed_lessons' => 0,
            'completed_exercises' => 0,
            'total_time_spent' => 0,
            'quiz_average_score' => 0
        ];

        // Count completed items
        $stats['completed_learning_paths'] = self::where('user_id', $userId)
            ->where('trackable_type', LearningPath::class)
            ->where('status', self::STATUS_COMPLETED)
            ->count();

        $stats['completed_units'] = self::where('user_id', $userId)
            ->where('trackable_type', Unit::class)
            ->where('status', self::STATUS_COMPLETED)
            ->count();

        $stats['completed_lessons'] = self::where('user_id', $userId)
            ->where('trackable_type', Lesson::class)
            ->where('status', self::STATUS_COMPLETED)
            ->count();

        $stats['completed_exercises'] = self::where('user_id', $userId)
            ->where('trackable_type', Exercise::class)
            ->where('status', self::STATUS_COMPLETED)
            ->count();

        // Calculate quiz average
        $quizScores = self::where('user_id', $userId)
            ->where('trackable_type', Quiz::class)
            ->where('status', self::STATUS_COMPLETED)
            ->get()
            ->pluck('meta_data.score');

        $stats['quiz_average_score'] = $quizScores->isNotEmpty() 
            ? round($quizScores->average(), 2) 
            : 0;

        // Calculate total time spent from meta_data
        $stats['total_time_spent'] = self::where('user_id', $userId)
            ->whereNotNull('meta_data->time_spent')
            ->get()
            ->sum(function ($progress) {
                return $progress->meta_data['time_spent'] ?? 0;
            });

        return $stats;
    }

    /**
     * Get the next incomplete item for a user
     */
    public static function getNextIncompleteItem(int $userId, string $trackableType): ?Model
    {
        $lastCompleted = self::where('user_id', $userId)
            ->where('trackable_type', $trackableType)
            ->where('status', self::STATUS_COMPLETED)
            ->orderByDesc('updated_at')
            ->first();

        if (!$lastCompleted) {
            // If no completed items, get the first item of this type
            return $trackableType::orderBy('id')->first();
        }

        // Get the next item based on order (if available) or ID
        if (method_exists($lastCompleted->trackable, 'order')) {
            return $trackableType::where('order', '>', $lastCompleted->trackable->order)
                ->orderBy('order')
                ->first();
        }

        return $trackableType::where('id', '>', $lastCompleted->trackable_id)
            ->orderBy('id')
            ->first();
    }
}