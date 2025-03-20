<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasMedia;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class QuizQuestion extends Model
{
    use HasFactory, HasVersions, HasAuditLog, HasMedia;

    const AUDIT_AREA = 'quiz_questions';

    const TYPE_SINGLE_CHOICE = 'single_choice';
    const TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    const TYPE_TRUE_FALSE = 'true_false';
    const TYPE_SHORT_ANSWER = 'short_answer';

    protected $fillable = [
        'quiz_id',
        'question',
        'type',
        'options',
        'correct_answer',
        'explanation',
        'points',
        'order'
    ];

    protected $casts = [
        'options' => 'array',
        'correct_answer' => 'array',
        'points' => 'integer',
        'order' => 'integer'
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'question',
        'type',
        'options',
        'correct_answer',
        'explanation',
        'points',
        'order'
    ];

    /**
     * Get the quiz that owns the question.
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Get attempts statistics for this question
     */
    public function getAttemptStats(): array
    {
        $stats = DB::table('quiz_attempts')
            ->where('quiz_id', $this->quiz_id)
            ->whereJsonContains('question_results', ['question_id' => $this->id])
            ->select([
                DB::raw('COUNT(*) as total_attempts'),
                DB::raw('SUM(JSON_EXTRACT(question_results, "$[*].correct")) as correct_attempts')
            ])
            ->first();

        return [
            'total_attempts' => $stats->total_attempts ?? 0,
            'correct_attempts' => $stats->correct_attempts ?? 0,
            'success_rate' => $stats->total_attempts > 0 
                ? ($stats->correct_attempts / $stats->total_attempts) * 100 
                : 0
        ];
    }

    /**
     * Get correct answer for feedback (only when student answered incorrectly)
     */
    public function getCorrectAnswer(): string|array
    {
        return match($this->type) {
            self::TYPE_SINGLE_CHOICE, self::TYPE_TRUE_FALSE => $this->correct_answer['value'],
            self::TYPE_MULTIPLE_CHOICE => $this->correct_answer['values'],
            self::TYPE_SHORT_ANSWER => $this->correct_answer['value'],
            default => ''
        };
    }

    /**
     * Check if the given answer is correct
     */
    public function checkAnswer($answer): bool
    {
        return match($this->type) {
            self::TYPE_SINGLE_CHOICE => $this->checkSingleChoice($answer),
            self::TYPE_MULTIPLE_CHOICE => $this->checkMultipleChoice($answer),
            self::TYPE_TRUE_FALSE => $this->checkTrueFalse($answer),
            self::TYPE_SHORT_ANSWER => $this->checkShortAnswer($answer),
            default => false
        };
    }

    /**
     * Check single choice answer
     */
    private function checkSingleChoice($answer): bool
    {
        return $answer === $this->correct_answer['value'];
    }

    /**
     * Check multiple choice answer
     */
    private function checkMultipleChoice($answers): bool
    {
        if (!is_array($answers)) {
            return false;
        }

        sort($answers);
        $correct = $this->correct_answer['values'];
        sort($correct);

        return $answers === $correct;
    }

    /**
     * Check true/false answer
     */
    private function checkTrueFalse($answer): bool
    {
        return $answer === $this->correct_answer['value'];
    }

    /**
     * Check short answer
     */
    private function checkShortAnswer($answer): bool
    {
        $correct = $this->correct_answer['value'];
        
        if (is_array($this->correct_answer['alternatives'] ?? null)) {
            return in_array(
                strtolower(trim($answer)),
                array_map('strtolower', array_map('trim', 
                    array_merge([$correct], $this->correct_answer['alternatives'])
                ))
            );
        }

        return strtolower(trim($answer)) === strtolower(trim($correct));
    }

    /**
     * Get the export data structure
     */
    public function getExportData(): array
    {
        return [
            'question' => $this->question,
            'type' => $this->type,
            'options' => $this->options,
            'correct_answer' => $this->correct_answer,
            'explanation' => $this->explanation,
            'points' => $this->points,
            'order' => $this->order,
            'media' => $this->media->groupBy('collection_name')->toArray(),
        ];
    }

    /**
     * Import data from an export structure
     */
    public static function importData(array $data, Quiz $quiz): self
    {
        return static::create([
            'quiz_id' => $quiz->id,
            'question' => $data['question'],
            'type' => $data['type'],
            'options' => $data['options'],
            'correct_answer' => $data['correct_answer'],
            'explanation' => $data['explanation'],
            'points' => $data['points'],
            'order' => $data['order']
        ]);
    }

    /**
     * Get question validation rules by type
     */
    public static function getValidationRules(string $type): array
    {
        return match($type) {
            self::TYPE_SINGLE_CHOICE => [
                'options' => 'required|array|min:2',
                'options.*' => 'required|string',
                'correct_answer.value' => 'required|string|in_array:options.*'
            ],
            self::TYPE_MULTIPLE_CHOICE => [
                'options' => 'required|array|min:2',
                'options.*' => 'required|string',
                'correct_answer.values' => 'required|array|min:1',
                'correct_answer.values.*' => 'required|string|in_array:options.*'
            ],
            self::TYPE_TRUE_FALSE => [
                'correct_answer.value' => 'required|boolean'
            ],
            self::TYPE_SHORT_ANSWER => [
                'correct_answer.value' => 'required|string',
                'correct_answer.alternatives' => 'nullable|array',
                'correct_answer.alternatives.*' => 'required|string'
            ],
            default => []
        };
    }

    /**
     * Get all media collections available for questions
     */
    public static function getMediaCollections(): array
    {
        return [
            'question_images' => [
                'max_files' => 1,
                'conversions' => [
                    'thumb' => ['width' => 100, 'height' => 100],
                    'display' => ['width' => 600, 'height' => null]
                ]
            ],
            'audio' => [
                'max_files' => 1,
                'allowed_types' => ['audio/mpeg', 'audio/wav']
            ]
        ];
    }
}