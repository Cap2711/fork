<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question',
        'options',
        'correct_answer'
    ];

    protected $casts = [
        'options' => 'json'
    ];

    /**
     * Get the quiz that owns the question.
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Check if the given answer is correct
     */
    public function checkAnswer($answer): bool
    {
        // Case-insensitive comparison for text answers
        if (is_string($answer) && is_string($this->correct_answer)) {
            return strtolower(trim($answer)) === strtolower(trim($this->correct_answer));
        }

        // Strict comparison for other types (e.g., multiple choice options)
        return $answer === $this->correct_answer;
    }

    /**
     * Get the question with masked correct answer
     */
    public function getPublicAttributes(): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'options' => $this->options
        ];
    }

    /**
     * Create a new question with randomized option order
     */
    public static function createWithRandomizedOptions(array $attributes): self
    {
        if (isset($attributes['options']) && is_array($attributes['options'])) {
            // Randomize options order
            shuffle($attributes['options']);
        }

        return static::create($attributes);
    }

    /**
     * Scope a query to randomly order questions
     */
    public function scopeRandomOrder($query)
    {
        return $query->inRandomOrder();
    }
}