<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use App\Models\Lesson;
use App\Models\User;
use App\Models\UserLessonProgress;
use App\Models\UserUnitProgress;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LearningController extends Controller
{
    public function getUnits(): JsonResponse
    {
        $user = Auth::user();
        $units = Unit::with('lessons')->orderBy('order')->get();

        $unitsData = $units->map(function ($unit) use ($user) {
            $progress = $unit->userProgress()
                ->where('user_id', $user->id)
                ->first();

            $lessonProgress = $unit->userLessonProgress()
                ->where('user_id', $user->id)
                ->get();

            return [
                'id' => $unit->id,
                'name' => $unit->name,
                'description' => $unit->description,
                'difficulty' => $unit->difficulty,
                'order' => $unit->order,
                'is_locked' => !$unit->isAvailableForUser($user),
                'level' => $progress ? $progress->level : 0,
                'crown_color' => $progress ? $progress->getCrownColor() : 'none',
                'total_lessons' => $unit->lessons->count(),
                'completed_lessons' => $lessonProgress->where('completed', true)->count(),
                'needs_practice' => $progress ? $progress->isEligibleForPractice() : false,
            ];
        });

        return response()->json($unitsData);
    }

    public function getUnitLessons(Unit $unit): JsonResponse
    {
        $user = Auth::user();
        $lessons = $unit->lessons()
            ->orderBy('order')
            ->get()
            ->map(function ($lesson) use ($user) {
                $progress = $lesson->userProgress()
                    ->where('user_id', $user->id)
                    ->first();

                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'description' => $lesson->description,
                    'type' => $lesson->type,
                    'order' => $lesson->order,
                    'xp_reward' => $lesson->xp_reward,
                    'completed' => $progress ? $progress->completed : false,
                    'score' => $progress ? $progress->score : null,
                    'mastery_level' => $progress ? $progress->getMasteryLevel() : 'not_started',
                    'completed_at' => $progress ? $progress->completed_at : null,
                    'is_locked' => !$lesson->isAvailableForUser($user),
                    'needs_review' => $progress ? $progress->shouldReview() : false,
                ];
            });

        return response()->json($lessons);
    }

    public function getLessonContent(Lesson $lesson): JsonResponse
    {
        $user = Auth::user();

        if (!$lesson->isAvailableForUser($user)) {
            return response()->json(['error' => 'Lesson is locked'], 403);
        }

        $content = $lesson->getLessonContent();
        $progress = $lesson->userProgress()
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'type' => $lesson->type,
                'xp_reward' => $lesson->xp_reward,
            ],
            'progress' => $progress ? [
                'completed' => $progress->completed,
                'score' => $progress->score,
                'mastery_level' => $progress->getMasteryLevel(),
            ] : null,
            'content' => $content,
        ]);
    }

    public function completeLesson(Lesson $lesson, Request $request): JsonResponse
    {
        $request->validate([
            'score' => 'required|integer|min:0|max:100',
            'completed_items' => 'required|array',
            'completed_items.vocabulary.*' => 'exists:vocabulary_words,id',
            'completed_items.grammar.*' => 'exists:grammar_exercises,id',
            'completed_items.reading.*' => 'exists:reading_passages,id',
        ]);

        $user = Auth::user();
        $lesson->complete($user, $request->score);

        // Track which content items were completed
        foreach ($request->completed_items as $type => $ids) {
            foreach ($ids as $id) {
                switch ($type) {
                    case 'vocabulary':
                        $user->completedVocabulary()->attach($id, [
                            'lesson_id' => $lesson->id,
                            'completed_at' => now(),
                        ]);
                        break;
                    case 'grammar':
                        $user->completedGrammar()->attach($id, [
                            'lesson_id' => $lesson->id,
                            'completed_at' => now(),
                        ]);
                        break;
                    case 'reading':
                        $user->completedReading()->attach($id, [
                            'lesson_id' => $lesson->id,
                            'completed_at' => now(),
                        ]);
                        break;
                }
            }
        }

        // Get updated unit progress
        $unitProgress = $lesson->unit->userProgress()
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'success' => true,
            'xp_gained' => $lesson->xp_reward,
            'unit_level' => $unitProgress ? $unitProgress->level : 0,
            'crown_color' => $unitProgress ? $unitProgress->getCrownColor() : 'none',
            'mastery_level' => $lesson->userProgress()
                ->where('user_id', $user->id)
                ->first()
                ->getMasteryLevel(),
        ]);
    }

    public function getUserProgress(): JsonResponse
    {
        $user = Auth::user();
        
        $progress = [
            'total_exercises' => UserLessonProgress::where('user_id', $user->id)->count(),
            'completed_exercises' => UserLessonProgress::where('user_id', $user->id)
                ->where('completed', true)
                ->count(),
            'points' => $user->total_points,
            'streak_days' => $user->current_streak,
            'current_unit' => Unit::whereHas('userProgress', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->max('order') ?? 1,
            'vocabulary_mastered' => $user->completedVocabulary()->count(),
            'grammar_mastered' => $user->completedGrammar()->count(),
            'reading_completed' => $user->completedReading()->count(),
        ];

        return response()->json($progress);
    }

    public function practiceUnit(Unit $unit): JsonResponse
    {
        $user = Auth::user();
        
        if (!$unit->isAvailableForUser($user)) {
            return response()->json([
                'error' => 'Unit is locked'
            ], 403);
        }

        // Get lessons that need practice
        $lessonsToReview = $unit->lessons()
            ->whereHas('userProgress', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where(function ($q) {
                        $q->where('score', '<', 80)
                            ->orWhere('completed_at', '<', now()->subDays(30));
                    });
            })
            ->get()
            ->map(function ($lesson) use ($user) {
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'type' => $lesson->type,
                    'content' => $lesson->getLessonContent(),
                    'previous_score' => $lesson->getUserScore($user),
                ];
            });

        return response()->json([
            'lessons' => $lessonsToReview,
        ]);
    }
}