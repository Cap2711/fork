<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\AsCollection;

class Exercise extends Model
{
    protected $fillable = [
        'lesson_id',
        'exercise_type_id',
        'order',
        'prompt',
        'content',
        'correct_answer',
        'distractors',
        'xp_reward',
    ];

    protected $casts = [
        'content' => AsCollection::class,
        'correct_answer' => AsCollection::class,
        'distractors' => AsCollection::class,
        'order' => 'integer',
        'xp_reward' => 'integer',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ExerciseType::class, 'exercise_type_id');
    }

    public function hints(): HasMany
    {
        return $this->hasMany(ExerciseHint::class)->orderBy('order');
    }

    public function userProgress(): HasMany
    {
        return $this->hasMany(UserExerciseProgress::class);
    }

    public function validateAnswer(string|array $answer): bool
    {
        switch ($this->type->name) {
            case 'multiple_choice':
                return $this->correct_answer->first() === $answer;

            case 'translate':
            case 'listen_type':
                // Case-insensitive string comparison
                return strtolower($this->correct_answer->first()) === strtolower($answer);

            case 'word_bank':
            case 'fill_in_blank':
                // Array comparison - order matters
                if (!is_array($answer)) return false;
                return $this->correct_answer->toArray() === $answer;

            case 'match_pairs':
                // Array comparison for pairs - order doesn't matter
                if (!is_array($answer)) return false;
                $correctPairs = $this->correct_answer->toArray();
                sort($correctPairs);
                sort($answer);
                return $correctPairs === $answer;

            case 'speak':
                // Speech recognition comparison with some tolerance
                if (!is_string($answer)) return false;
                $correctText = strtolower($this->correct_answer->first());
                $userText = strtolower($answer);
                // Allow for some variation in speech recognition
                similar_text($correctText, $userText, $percentage);
                return $percentage >= 85;

            default:
                return false;
        }
    }

    public function getHint(int $attemptCount): ?string
    {
        // Return appropriate hint based on number of attempts
        return $this->hints()
            ->where('order', $attemptCount)
            ->value('hint');
    }

    public function completeForUser(User $user, string|array $answer, bool $usedHint = false): void
    {
        $isCorrect = $this->validateAnswer($answer);
        
        $progress = $this->userProgress()->firstOrNew([
            'user_id' => $user->id,
        ]);

        $progress->attempts++;
        $progress->correct = $isCorrect;
        $progress->user_answer = is_array($answer) ? json_encode($answer) : $answer;

        if ($isCorrect && !$progress->completed) {
            $progress->completed = true;
            $progress->completed_at = now();

            // Award XP, reduced if hint was used
            $xpAward = $usedHint ? 
                max(0, $this->xp_reward - $this->hints()->sum('xp_penalty')) : 
                $this->xp_reward;

            $user->awardXp($xpAward, 'exercise_completion', $this->id);
        }

        $progress->save();
    }

    public function getProgressForUser(User $user): array
    {
        $progress = $this->userProgress()
            ->where('user_id', $user->id)
            ->first();

        return [
            'completed' => $progress?->completed ?? false,
            'attempts' => $progress?->attempts ?? 0,
            'correct' => $progress?->correct ?? null,
            'last_attempt' => $progress?->user_answer,
            'completed_at' => $progress?->completed_at,
        ];
    }
}