<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuideBookEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'topic',
        'content'
    ];

    /**
     * Get the unit that owns the guide book entry.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Scope a query to search guide book entries.
     */
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($query) use ($term) {
            $query->where('topic', 'like', "%{$term}%")
                ->orWhere('content', 'like', "%{$term}%");
        });
    }

    /**
     * Get guide book entries for a specific target level
     */
    public function scopeByTargetLevel($query, string $level)
    {
        return $query->whereHas('unit.learningPath', function ($query) use ($level) {
            $query->where('target_level', $level);
        });
    }

    /**
     * Get a summary of the guide book entry
     */
    public function getSummary(int $maxLength = 200): array
    {
        return [
            'id' => $this->id,
            'topic' => $this->topic,
            'summary' => strlen($this->content) > $maxLength 
                ? substr($this->content, 0, $maxLength) . '...'
                : $this->content,
            'unit' => [
                'id' => $this->unit->id,
                'title' => $this->unit->title,
                'learning_path' => [
                    'id' => $this->unit->learningPath->id,
                    'title' => $this->unit->learningPath->title
                ]
            ]
        ];
    }
}