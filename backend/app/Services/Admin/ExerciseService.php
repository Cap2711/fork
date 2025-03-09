<?php

namespace App\Services\Admin;

use App\Models\Exercise;
use App\Models\ExerciseType;
use App\Models\ExerciseHint;
use App\Models\Lesson;
use App\Models\UserExerciseProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExerciseService
{
    public function listTypes(): Collection
    {
        return ExerciseType::withCount('exercises')
            ->get()
            ->map(function ($type) {
                return array_merge($type->toArray(), [
                    'usage_stats' => $this->getTypeUsageStats($type)
                ]);
            });
    }

    public function createType(array $data): ExerciseType
    {
        return ExerciseType::create($data);
    }

    public function createExercise(Lesson $lesson, array $data): Exercise
    {
        return DB::transaction(function () use ($lesson, $data) {
            // Reorder existing exercises if necessary
            if ($lesson->exercises()->where('order', '>=', $data['order'])->exists()) {
                $lesson->exercises()
                    ->where('order', '>=', $data['order'])
                    ->increment('order');
            }

            $exercise = $lesson->exercises()->create($data);

            // Create hints if provided
            if (isset($data['hints'])) {
                foreach ($data['hints'] as $hintData) {
                    $exercise->hints()->create($hintData);
                }
            }

            return $exercise->load('hints');
        });
    }

    public function updateExercise(Exercise $exercise, array $data): Exercise
    {
        return DB::transaction(function () use ($exercise, $data) {
            if (isset($data['order']) && $data['order'] !== $exercise->order) {
                $this->reorderExercises($exercise, $data['order']);
            }

            // Update hints if provided
            if (isset($data['hints'])) {
                $this->updateHints($exercise, $data['hints']);
            }

            $exercise->update($data);
            return $exercise->fresh(['hints']);
        });
    }

    public function deleteExercise(Exercise $exercise): void
    {
        DB::transaction(function () use ($exercise) {
            // Update order of remaining exercises
            Exercise::where('lesson_id', $exercise->lesson_id)
                ->where('order', '>', $exercise->order)
                ->decrement('order');
            
            $exercise->delete();
        });
    }

    public function cloneExercise(Exercise $exercise): Exercise
    {
        return DB::transaction(function () use ($exercise) {
            // Get max order in lesson
            $maxOrder = Exercise::where('lesson_id', $exercise->lesson_id)
                ->max('order');

            // Clone exercise
            $newExercise = $exercise->replicate();
            $newExercise->order = $maxOrder + 1;
            $newExercise->save();

            // Clone hints
            foreach ($exercise->hints as $hint) {
                $newHint = $hint->replicate();
                $newHint->exercise_id = $newExercise->id;
                $newHint->save();
            }

            return $newExercise->load('hints');
        });
    }

    public function attachStats(Exercise $exercise): array
    {
        $progress = UserExerciseProgress::where('exercise_id', $exercise->id);
        $totalAttempts = $progress->count();
        $completions = $progress->where('completed', true)->count();
        $avgScore = $progress->avg('score');

        return array_merge($exercise->toArray(), [
            'total_attempts' => $totalAttempts,
            'completion_count' => $completions,
            'completion_rate' => $totalAttempts > 0 ? 
                round(($completions / $totalAttempts) * 100, 2) : 0,
            'average_score' => $avgScore ? round($avgScore, 2) : 0,
            'average_attempts' => $progress->avg('attempts') ?? 0,
            'hint_usage_rate' => $this->calculateHintUsageRate($exercise),
        ]);
    }

    private function getTypeUsageStats(ExerciseType $type): array
    {
        $exercises = $type->exercises();
        $totalAttempts = UserExerciseProgress::whereIn('exercise_id', $exercises->pluck('id'))->count();
        $completions = UserExerciseProgress::whereIn('exercise_id', $exercises->pluck('id'))
            ->where('completed', true)
            ->count();

        return [
            'total_exercises' => $exercises->count(),
            'total_attempts' => $totalAttempts,
            'completion_rate' => $totalAttempts > 0 ? 
                round(($completions / $totalAttempts) * 100, 2) : 0,
            'average_score' => UserExerciseProgress::whereIn('exercise_id', $exercises->pluck('id'))
                ->avg('score') ?? 0,
        ];
    }

    private function reorderExercises(Exercise $exercise, int $newOrder): void
    {
        if ($newOrder > $exercise->order) {
            Exercise::where('lesson_id', $exercise->lesson_id)
                ->whereBetween('order', [$exercise->order + 1, $newOrder])
                ->decrement('order');
        } else {
            Exercise::where('lesson_id', $exercise->lesson_id)
                ->whereBetween('order', [$newOrder, $exercise->order - 1])
                ->increment('order');
        }
        $exercise->update(['order' => $newOrder]);
    }

    private function updateHints(Exercise $exercise, array $hints): void
    {
        // Delete removed hints
        $hintIds = collect($hints)->pluck('id')->filter();
        $exercise->hints()->whereNotIn('id', $hintIds)->delete();

        // Update or create hints
        foreach ($hints as $hintData) {
            if (isset($hintData['id'])) {
                $exercise->hints()->where('id', $hintData['id'])
                    ->update($hintData);
            } else {
                $exercise->hints()->create($hintData);
            }
        }
    }

    private function calculateHintUsageRate(Exercise $exercise): float
    {
        $totalAttempts = UserExerciseProgress::where('exercise_id', $exercise->id)->count();
        if ($totalAttempts === 0) return 0;

        $hintsUsed = UserExerciseProgress::where('exercise_id', $exercise->id)
            ->whereNotNull('hint_used_at')
            ->count();

        return round(($hintsUsed / $totalAttempts) * 100, 2);
    }
}