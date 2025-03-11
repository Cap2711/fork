<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'type',
        'content',
        'answers',
        'order'
    ];

    protected $casts = [
        'content' => 'json',
        'answers' => 'json',
        'order' => 'integer'
    ];

    /**
     * Valid exercise types
     */
    const TYPES = [
        'multiple_choice',
        'fill_blank',
        'matching',
        'writing',
        'speaking'
    ];

    /**
     * Get the section that owns the exercise.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get all progress records for this exercise.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Check if the given answer is correct
     */
    public function checkAnswer($userAnswer): bool
    {
        switch ($this->type) {
            case 'multiple_choice':
                return $this->checkMultipleChoice($userAnswer);
            
            case 'fill_blank':
                return $this->checkFillBlank($userAnswer);
            
            case 'matching':
                return $this->checkMatching($userAnswer);
            
            case 'writing':
            case 'speaking':
                // These types require manual review
                return false;
            
            default:
                return false;
        }
    }

    /**
     * Check multiple choice answer
     */
    private function checkMultipleChoice($answer): bool
    {
        return $answer === $this->answers['correct'];
    }

    /**
     * Check fill in the blank answer
     */
    private function checkFillBlank($answer): bool
    {
        // Case-insensitive comparison
        return strtolower($answer) === strtolower($this->answers['correct']);
    }

    /**
     * Check matching answer
     */
    private function checkMatching($answers): bool
    {
        if (!is_array($answers) || count($answers) !== count($this->answers['correct'])) {
            return false;
        }

        foreach ($this->answers['correct'] as $key => $value) {
            if (!isset($answers[$key]) || $answers[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mark the exercise as completed for a user
     */
    public function complete(int $userId, array $metadata = []): void
    {
        $this->progress()->updateOrCreate(
            ['user_id' => $userId],
            [
                'status' => 'completed',
                'meta_data' => $metadata
            ]
        );
    }
}