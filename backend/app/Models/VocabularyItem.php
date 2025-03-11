<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VocabularyItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'word',
        'translation',
        'example'
    ];

    /**
     * Get the lesson that owns the vocabulary item.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Scope a query to search vocabulary items.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($query) use ($term) {
            $query->where('word', 'like', "%{$term}%")
                ->orWhere('translation', 'like', "%{$term}%")
                ->orWhere('example', 'like', "%{$term}%");
        });
    }

    /**
     * Get vocabulary items for a specific target level
     */
    public function scopeByTargetLevel($query, string $level)
    {
        return $query->whereHas('lesson.unit.learningPath', function ($query) use ($level) {
            $query->where('target_level', $level);
        });
    }

    /**
     * Format the vocabulary item for review
     */
    public function getReviewFormat(): array
    {
        return [
            'id' => $this->id,
            'word' => $this->word,
            'translation' => $this->translation,
            'example' => $this->example,
            'lesson' => [
                'id' => $this->lesson->id,
                'title' => $this->lesson->title,
                'unit' => [
                    'id' => $this->lesson->unit->id,
                    'title' => $this->lesson->unit->title
                ]
            ]
        ];
    }
}