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
        'unit_id',
        'title',
        'passing_score'
    ];

    protected $casts = [
        'passing_score' => 'integer'
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
     * Submit a quiz attempt and record progress
     */
    public function submitAttempt(int $userId, array $answers): array
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

    /**
     * Get quiz statistics
     */
    public function getStatistics(): array
    {
        $attempts = $this->progress()->get();
        $totalAttempts = $attempts->count();

        if ($totalAttempts === 0) {
            return [
                'total_attempts' => 0,
                'average_score' => 0,
                'pass_rate' => 0,
                'completion_rate' => 0
            ];
        }

        $passedAttempts = $attempts->filter(function ($attempt) {
            return ($attempt->meta_data['score'] ?? 0) >= $this->passing_score;
        })->count();

        return [
            'total_attempts' => $totalAttempts,
            'average_score' => $attempts->avg(fn($a) => $a->meta_data['score'] ?? 0),
            'pass_rate' => ($passedAttempts / $totalAttempts) * 100,
            'completion_rate' => ($attempts->unique('user_id')->count() / User::count()) * 100
        ];
    }
}