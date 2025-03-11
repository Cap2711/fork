<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'title',
        'description',
        'order'
    ];

    protected $casts = [
        'order' => 'integer'
    ];

    /**
     * Get the unit that owns the lesson.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the learning path through the unit.
     */
    public function learningPath()
    {
        return $this->unit->learningPath();
    }

    /**
     * Get the sections for the lesson.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }

    /**
     * Get the vocabulary items for the lesson.
     */
    public function vocabularyItems(): HasMany
    {
        return $this->hasMany(VocabularyItem::class);
    }

    /**
     * Get all progress records for this lesson.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Check if all sections are completed for a user
     */
    public function isCompletedByUser(int $userId): bool
    {
        $totalSections = $this->sections()->count();
        if ($totalSections === 0) {
            return false;
        }

        $completedSections = $this->sections()
            ->whereHas('progress', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', 'completed');
            })
            ->count();

        return $completedSections === $totalSections;
    }

    /**
     * Get the next lesson in the unit
     */
    public function getNextLesson()
    {
        return self::where('unit_id', $this->unit_id)
            ->where('order', '>', $this->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * Get the previous lesson in the unit
     */
    public function getPreviousLesson()
    {
        return self::where('unit_id', $this->unit_id)
            ->where('order', '<', $this->order)
            ->orderBy('order', 'desc')
            ->first();
    }
}