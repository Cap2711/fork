<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LearningPath extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'target_level',
        'status'
    ];

    protected $casts = [
        'status' => 'string'
    ];

    /**
     * Get the units for the learning path.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('order');
    }

    /**
     * Get all progress records for this learning path.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Get the published scope.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Check if the learning path is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Get the total number of lessons in this learning path.
     */
    public function getLessonCount(): int
    {
        return $this->units()->withCount('lessons')->get()->sum('lessons_count');
    }
}