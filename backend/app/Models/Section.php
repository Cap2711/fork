<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'title',
        'content',
        'order'
    ];

    protected $casts = [
        'order' => 'integer'
    ];

    /**
     * Get the lesson that owns the section.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the exercises for the section.
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('order');
    }

    /**
     * Get all progress records for this section.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Check if all exercises are completed for a user
     */
    public function isCompletedByUser(int $userId): bool
    {
        $totalExercises = $this->exercises()->count();
        if ($totalExercises === 0) {
            return $this->progress()
                ->where('user_id', $userId)
                ->where('status', 'completed')
                ->exists();
        }

        $completedExercises = $this->exercises()
            ->whereHas('progress', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', 'completed');
            })
            ->count();

        return $completedExercises === $totalExercises;
    }

    /**
     * Get the next section in the lesson
     */
    public function getNextSection()
    {
        return self::where('lesson_id', $this->lesson_id)
            ->where('order', '>', $this->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * Get the previous section in the lesson
     */
    public function getPreviousSection()
    {
        return self::where('lesson_id', $this->lesson_id)
            ->where('order', '<', $this->order)
            ->orderBy('order', 'desc')
            ->first();
    }
}