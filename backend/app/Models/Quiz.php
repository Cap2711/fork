<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'title',
        'passing_score'
    ];

    protected $casts = [
        'passing_score' => 'integer'
    ];

    /**
     * Get the unit that owns the quiz.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the questions for the quiz.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    /**
     * Get all progress records for this quiz.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Calculate the score for a set of answers
     */
    public function calculateScore(array $answers): float
    {
        $totalQuestions = $this->questions()->count();
        if ($totalQuestions === 0) {
            return 0;
        }

        $correctAnswers = 0;
        foreach ($answers as $questionId => $answer) {
            $question = $this->questions()->find($questionId);
            if ($question && $question->checkAnswer($answer)) {
                $correctAnswers++;
            }
        }

        return ($correctAnswers / $totalQuestions) * 100;
    }

    /**
     * Submit a quiz attempt for a user
     */
    public function submit(int $userId, array $answers): array
    {
        $score = $this->calculateScore($answers);
        $passed = $score >= $this->passing_score;

        $this->progress()->updateOrCreate(
            ['user_id' => $userId],
            [
                'status' => $passed ? 'completed' : 'failed',
                'meta_data' => [
                    'score' => $score,
                    'passed' => $passed,
                    'answers' => $answers,
                    'attempt_date' => now()
                ]
            ]
        );

        return [
            'score' => $score,
            'passed' => $passed,
            'required_score' => $this->passing_score
        ];
    }

    /**
     * Get the best score for a user
     */
    public function getBestScore(int $userId): ?float
    {
        $progress = $this->progress()
            ->where('user_id', $userId)
            ->get();

        if ($progress->isEmpty()) {
            return null;
        }

        return $progress->max(function ($attempt) {
            return $attempt->meta_data['score'] ?? 0;
        });
    }
}