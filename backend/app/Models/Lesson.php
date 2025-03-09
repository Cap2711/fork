<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Lesson extends Model
{
    protected $fillable = [
        'unit_id',
        'title',
        'description',
        'type',
        'order',
        'xp_reward',
    ];

    protected $casts = [
        'xp_reward' => 'integer',
        'order' => 'integer',
    ];

    // Parent unit relationship
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    // Content relationships
    public function vocabularyWords(): BelongsToMany
    {
        return $this->belongsToMany(VocabularyWord::class, 'lesson_vocabulary')
            ->withPivot('order')
            ->orderBy('lesson_vocabulary.order');
    }

    public function grammarExercises(): BelongsToMany
    {
        return $this->belongsToMany(GrammarExercise::class, 'lesson_grammar')
            ->withPivot('order')
            ->orderBy('lesson_grammar.order');
    }

    public function readingPassages(): BelongsToMany
    {
        return $this->belongsToMany(ReadingPassage::class, 'lesson_reading')
            ->withPivot('order')
            ->orderBy('lesson_reading.order');
    }

    // Progress tracking
    public function userProgress(): HasMany
    {
        return $this->hasMany(UserLessonProgress::class);
    }

    // Helper methods
    public function getLessonContent(): array
    {
        $content = [];

        switch ($this->type) {
            case 'vocabulary':
                $content['vocabulary'] = $this->vocabularyWords;
                break;
            case 'grammar':
                $content['grammar'] = $this->grammarExercises;
                break;
            case 'reading':
                $content['reading'] = $this->readingPassages;
                break;
            case 'mixed':
                $content['vocabulary'] = $this->vocabularyWords;
                $content['grammar'] = $this->grammarExercises;
                $content['reading'] = $this->readingPassages;
                break;
        }

        return $content;
    }

    public function isCompletedByUser(User $user): bool
    {
        return $this->userProgress()
            ->where('user_id', $user->id)
            ->where('completed', true)
            ->exists();
    }

    public function isAvailableForUser(User $user): bool
    {
        // First lesson in unit is always available
        if ($this->order === 1) {
            return true;
        }

        // Check if previous lesson is completed
        $previousLesson = $this->unit->lessons()
            ->where('order', $this->order - 1)
            ->first();

        return $previousLesson && $previousLesson->isCompletedByUser($user);
    }

    public function getUserScore(User $user): ?int
    {
        return $this->userProgress()
            ->where('user_id', $user->id)
            ->value('score');
    }

    public function complete(User $user, int $score = null): void
    {
        $progress = $this->userProgress()->firstOrNew(['user_id' => $user->id]);

        if (!$progress->completed) {
            $progress->completed = true;
            $progress->score = $score;
            $progress->completed_at = now();
            $progress->save();

            // Award XP
            $user->awardXp($this->xp_reward, 'lesson_completion', $this->id);

            // Update unit progress if all lessons are completed
            $unit = $this->unit;
            $completedLessons = $unit->getCompletedLessonsCount($user);
            $totalLessons = $unit->lessons()->count();

            if ($completedLessons === $totalLessons) {
                $unitProgress = $unit->userProgress()->firstOrNew(['user_id' => $user->id]);
                $unitProgress->level++;
                $unitProgress->save();

                // Award bonus XP for unit completion
                $user->awardXp(50, 'unit_completion', null);
            }
        }
    }
}