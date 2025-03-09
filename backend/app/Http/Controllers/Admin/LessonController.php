<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Unit;
use App\Services\Admin\LessonService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class LessonController extends Controller
{
    public function __construct(
        private LessonService $lessonService
    ) {}

    /**
     * List lessons for a unit
     */
    public function index(Unit $unit): JsonResponse
    {
        $lessons = $this->lessonService->listForUnit($unit);
        
        return response()->json([
            'unit' => [
                'id' => $unit->id,
                'name' => $unit->name,
            ],
            'lessons' => $lessons,
            'total' => $lessons->count(),
            'has_exercises' => $lessons->contains(fn($lesson) => $lesson['exercises_count'] > 0)
        ]);
    }

    /**
     * Create a new lesson
     */
    public function store(Unit $unit, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'type' => ['required', Rule::in(['mixed', 'vocabulary', 'grammar', 'reading'])],
            'order' => 'required|integer|min:1',
            'xp_reward' => 'required|integer|min:0',
        ]);

        $lesson = $this->lessonService->create($unit, $validated);

        return response()->json([
            'message' => 'Lesson created successfully',
            'lesson' => $this->lessonService->attachStats($lesson)
        ], 201);
    }

    /**
     * Update an existing lesson
     */
    public function update(Lesson $lesson, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'type' => ['sometimes', Rule::in(['mixed', 'vocabulary', 'grammar', 'reading'])],
            'order' => 'sometimes|integer|min:1',
            'xp_reward' => 'sometimes|integer|min:0',
        ]);

        $lesson = $this->lessonService->update($lesson, $validated);

        return response()->json([
            'message' => 'Lesson updated successfully',
            'lesson' => $this->lessonService->attachStats($lesson)
        ]);
    }

    /**
     * Delete a lesson
     */
    public function destroy(Lesson $lesson): JsonResponse
    {
        // Check if lesson has any completed exercises
        if ($lesson->hasCompletedExercises()) {
            return response()->json([
                'message' => 'Cannot delete lesson with completed exercises',
                'completed_exercises_count' => $lesson->completedExercisesCount()
            ], 422);
        }

        $this->lessonService->delete($lesson);

        return response()->json([
            'message' => 'Lesson deleted successfully'
        ]);
    }

    /**
     * Get lesson statistics
     */
    public function stats(Lesson $lesson): JsonResponse
    {
        return response()->json([
            'lesson_id' => $lesson->id,
            'stats' => $this->lessonService->attachStats($lesson)
        ]);
    }

    /**
     * Bulk update lesson order within a unit
     */
    public function updateOrder(Unit $unit, Request $request): JsonResponse
    {
        $request->validate([
            'lessons' => 'required|array',
            'lessons.*.id' => [
                'required',
                'exists:lessons,id',
                Rule::exists('lessons', 'id')->where(function ($query) use ($unit) {
                    $query->where('unit_id', $unit->id);
                }),
            ],
            'lessons.*.order' => 'required|integer|min:1'
        ]);

        foreach ($request->lessons as $lessonData) {
            $lesson = Lesson::find($lessonData['id']);
            if ($lesson && $lesson->order !== $lessonData['order']) {
                $this->lessonService->update($lesson, ['order' => $lessonData['order']]);
            }
        }

        return response()->json([
            'message' => 'Lesson order updated successfully',
            'lessons' => $this->lessonService->listForUnit($unit)
        ]);
    }

    /**
     * Clone a lesson within the same unit
     */
    public function clone(Lesson $lesson): JsonResponse
    {
        $clonedLesson = DB::transaction(function () use ($lesson) {
            // Get highest order in the unit
            $maxOrder = Lesson::where('unit_id', $lesson->unit_id)
                ->max('order');

            // Create clone with incremented order
            $data = $lesson->toArray();
            $data['order'] = $maxOrder + 1;
            $data['title'] = "Copy of {$lesson->title}";

            return $this->lessonService->create($lesson->unit, $data);
        });

        return response()->json([
            'message' => 'Lesson cloned successfully',
            'lesson' => $this->lessonService->attachStats($clonedLesson)
        ]);
    }
}