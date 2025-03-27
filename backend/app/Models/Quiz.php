<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Quiz extends Model
{
    use HasFactory, HasVersions, HasAuditLog;

    const AUDIT_AREA = 'quizzes';

    protected $fillable = [
        'lesson_id',
        'section_id',
        'title',
        'slug',
        'description',
        'type',
        'passing_score',
        'time_limit',
        'difficulty_level',
        'is_published'
    ];

    protected $casts = [
        'passing_score' => 'integer',
        'time_limit' => 'integer',
        'is_published' => 'boolean'
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'title',
        'passing_score'
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
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }

    /**
     * Get all progress records for this quiz.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Get all attempts for this quiz.
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
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
     * Get the best score for a user
     */
    public function getBestScore(int $userId): ?float
    {
        return $this->attempts()
            ->where('user_id', $userId)
            ->max('score');
    }

    /**
     * Check if a user has passed this quiz
     */
    public function hasUserPassed(int $userId): bool
    {
        return $this->attempts()
            ->where('user_id', $userId)
            ->where('passed', true)
            ->exists();
    }

    /**
     * Get question-level statistics
     */
    public function getQuestionStats(): array
    {
        $attempts = $this->attempts;
        if ($attempts->isEmpty()) {
            return [];
        }

        return $this->questions()
            ->get()
            ->map(function ($question) use ($attempts) {
                $questionResults = $attempts->pluck('question_results')
                    ->flatten(1)
                    ->filter(fn($result) => $result['question_id'] === $question->id);

                $totalAttempts = $questionResults->count();
                $correctAttempts = $questionResults->filter(fn($result) => $result['correct'])->count();

                return [
                    'question_id' => $question->id,
                    'question' => $question->question,
                    'success_rate' => $totalAttempts > 0 ? ($correctAttempts / $totalAttempts) * 100 : 0,
                    'total_attempts' => $totalAttempts
                ];
            })
            ->toArray();
    }

    /**
     * Get the export data structure
     */
    public function getExportData(): array
    {
        return [
            'title' => $this->title,
            'passing_score' => $this->passing_score,
            'questions' => $this->questions->map->getExportData()->toArray()
        ];
    }

    /**
     * Import data from an export structure
     */
    public static function importData(array $data, Unit $unit): self
    {
        $quiz = static::create([
            'unit_id' => $unit->id,
            'title' => $data['title'],
            'passing_score' => $data['passing_score']
        ]);

        foreach ($data['questions'] ?? [] as $questionData) {
            QuizQuestion::importData($questionData, $quiz);
        }

        return $quiz;
    }
}