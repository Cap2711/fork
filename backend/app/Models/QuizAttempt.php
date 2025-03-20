<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    protected $fillable = [
        'quiz_id',
        'user_id',
        'answers',
        'score',
        'passed',
        'time_taken_seconds',
        'question_results'
    ];

    protected $casts = [
        'answers' => 'array',
        'score' => 'float',
        'passed' => 'boolean',
        'time_taken_seconds' => 'integer',
        'question_results' => 'array'
    ];

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get count of correct answers
     */
    public function getCorrectAnswersCount(): int
    {
        return collect($this->question_results)
            ->filter(fn($result) => $result['correct'])
            ->count();
    }

    /**
     * Get pass rate for this quiz attempt
     */
    public function getPassRate(): float
    {
        $totalQuestions = count($this->question_results ?? []);
        if ($totalQuestions === 0) {
            return 0.0;
        }

        return ($this->getCorrectAnswersCount() / $totalQuestions) * 100;
    }

    /**
     * Get statistics for specific questions
     */
    public function getQuestionStats(): array
    {
        if (!$this->question_results) {
            return [];
        }

        return collect($this->question_results)
            ->map(function ($result) {
                return [
                    'question_id' => $result['question_id'],
                    'correct' => $result['correct'],
                    'time_spent' => $result['time_spent'] ?? null,
                    'answer_given' => $result['answer']
                ];
            })
            ->toArray();
    }
}