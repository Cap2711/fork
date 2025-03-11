<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_path_id',
        'title',
        'description',
        'order'
    ];

    protected $casts = [
        'order' => 'integer'
    ];

    /**
     * Get the learning path that owns the unit.
     */
    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    /**
     * Get the lessons for the unit.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    /**
     * Get the quizzes for the unit.
     */
    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    /**
     * Get the guide book entries for the unit.
     */
    public function guideBookEntries(): HasMany
    {
        return $this->hasMany(GuideBookEntry::class);
    }

    /**
     * Get all progress records for this unit.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Get the completion percentage for a specific user
     */
    public function getCompletionPercentage(int $userId): float
    {
        $totalLessons = $this->lessons()->count();
        if ($totalLessons === 0) {
            return 0;
        }

        $completedLessons = $this->lessons()
            ->whereHas('progress', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', 'completed');
            })
            ->count();

        return ($completedLessons / $totalLessons) * 100;
    }
}