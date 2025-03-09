<?php

namespace App\Services\Admin;

use App\Models\Lesson;
use App\Models\Unit;
use App\Models\UserLessonProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LessonService
{
    public function listForUnit(Unit $unit): Collection
    {
        return $unit->lessons()
            ->with(['exercises' => function ($query) {
                $query->orderBy('order');
            }])
            ->orderBy('order')
            ->get()
            ->map(function ($lesson) {
                return $this->attachStats($lesson);
            });
    }

    public function create(Unit $unit, array $data): Lesson
    {
        return DB::transaction(function () use ($unit, $data) {
            // Reorder existing lessons if necessary
            if ($unit->lessons()->where('order', '>=', $data['order'])->exists()) {
                $unit->lessons()
                    ->where('order', '>=', $data['order'])
                    ->increment('order');
            }

            $lesson = $unit->lessons()->create($data);

            // Initialize content relationships based on lesson type
            $this->initializeLessonContent($lesson);

            return $lesson;
        });
    }

    public function update(Lesson $lesson, array $data): Lesson
    {
        return DB::transaction(function () use ($lesson, $data) {
            if (isset($data['order']) && $data['order'] !== $lesson->order) {
                $this->reorderLessons($lesson, $data['order']);
            }

            // If type is changing, handle content reorganization
            if (isset($data['type']) && $data['type'] !== $lesson->type) {
                $this->handleTypeChange($lesson, $data['type']);
            }

            $lesson->update($data);
            return $lesson->fresh();
        });
    }

    public function delete(Lesson $lesson): void
    {
        DB::transaction(function () use ($lesson) {
            // Update order of remaining lessons in the unit
            Lesson::where('unit_id', $lesson->unit_id)
                ->where('order', '>', $lesson->order)
                ->decrement('order');
            
            // Delete associated content
            $this->cleanupLessonContent($lesson);
            
            $lesson->delete();
        });
    }

    public function attachStats(Lesson $lesson): array
    {
        $progress = UserLessonProgress::where('lesson_id', $lesson->id);
        $totalAttempts = $progress->count();
        $completions = $progress->where('completed', true)->count();
        $avgScore = $progress->avg('score');

        return array_merge($lesson->toArray(), [
            'total_attempts' => $totalAttempts,
            'completion_count' => $completions,
            'completion_rate' => $totalAttempts > 0 ? 
                round(($completions / $totalAttempts) * 100, 2) : 0,
            'average_score' => $avgScore ? round($avgScore, 2) : 0,
            'average_completion_time' => $this->calculateAverageCompletionTime($lesson),
            'exercises_count' => $lesson->exercises()->count(),
        ]);
    }

    private function reorderLessons(Lesson $lesson, int $newOrder): void
    {
        if ($newOrder > $lesson->order) {
            Lesson::where('unit_id', $lesson->unit_id)
                ->whereBetween('order', [$lesson->order + 1, $newOrder])
                ->decrement('order');
        } else {
            Lesson::where('unit_id', $lesson->unit_id)
                ->whereBetween('order', [$newOrder, $lesson->order - 1])
                ->increment('order');
        }
        $lesson->update(['order' => $newOrder]);
    }

    private function initializeLessonContent(Lesson $lesson): void
    {
        // Create default content based on lesson type
        switch ($lesson->type) {
            case 'vocabulary':
                // Initialize vocabulary word slots
                break;
            case 'grammar':
                // Initialize grammar exercise structure
                break;
            case 'reading':
                // Initialize reading passage structure
                break;
            case 'mixed':
                // Initialize mixed content structure
                break;
        }
    }

    private function handleTypeChange(Lesson $lesson, string $newType): void
    {
        // Clean up old content
        $this->cleanupLessonContent($lesson);
        
        // Initialize new content structure
        $lesson->type = $newType;
        $this->initializeLessonContent($lesson);
    }

    private function cleanupLessonContent(Lesson $lesson): void
    {
        // Remove content based on lesson type
        switch ($lesson->type) {
            case 'vocabulary':
                $lesson->vocabularyWords()->detach();
                break;
            case 'grammar':
                $lesson->grammarExercises()->detach();
                break;
            case 'reading':
                $lesson->readingPassages()->detach();
                break;
            case 'mixed':
                // Clean up all content types
                $lesson->vocabularyWords()->detach();
                $lesson->grammarExercises()->detach();
                $lesson->readingPassages()->detach();
                break;
        }
    }

    private function calculateAverageCompletionTime(Lesson $lesson): ?float
    {
        return UserLessonProgress::where('lesson_id', $lesson->id)
            ->whereNotNull('completed_at')
            ->avg(DB::raw('TIMESTAMPDIFF(SECOND, created_at, completed_at)'));
    }
}