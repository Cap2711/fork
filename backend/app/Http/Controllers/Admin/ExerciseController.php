<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseType;
use App\Models\Lesson;
use App\Services\Admin\ExerciseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ExerciseController extends Controller
{
    public function __construct(
        private ExerciseService $exerciseService
    ) {}

    /**
     * List all exercise types with usage stats
     */
    public function listTypes(): JsonResponse
    {
        $types = $this->exerciseService->listTypes();
        
        return response()->json([
            'types' => $types,
            'total' => $types->count()
        ]);
    }

    /**
     * Create a new exercise type
     */
    public function createType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:exercise_types,name',
            'description' => 'required|string',
            'component_name' => 'required|string|unique:exercise_types,component_name'
        ]);

        $type = $this->exerciseService->createType($validated);

        return response()->json([
            'message' => 'Exercise type created successfully',
            'type' => $type
        ], 201);
    }

    /**
     * Update an exercise type
     */
    public function updateType(ExerciseType $type, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', Rule::unique('exercise_types')->ignore($type->id)],
            'description' => 'sometimes|string',
            'component_name' => ['sometimes', 'string', Rule::unique('exercise_types')->ignore($type->id)]
        ]);

        $type->update($validated);

        return response()->json([
            'message' => 'Exercise type updated successfully',
            'type' => $type->fresh()
        ]);
    }

    /**
     * Create a new exercise in a lesson
     */
    public function create(Lesson $lesson, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exercise_type_id' => 'required|exists:exercise_types,id',
            'prompt' => 'required|string',
            'content' => 'required|json',
            'correct_answer' => 'required|json',
            'distractors' => 'nullable|json',
            'order' => 'required|integer|min:1',
            'xp_reward' => 'required|integer|min:0',
            'hints' => 'array',
            'hints.*.hint' => 'required|string',
            'hints.*.order' => 'required|integer|min:0',
            'hints.*.xp_penalty' => 'required|integer|min:0',
        ]);

        $exercise = $this->exerciseService->createExercise($lesson, $validated);

        return response()->json([
            'message' => 'Exercise created successfully',
            'exercise' => $this->exerciseService->attachStats($exercise)
        ], 201);
    }

    /**
     * Update an existing exercise
     */
    public function update(Exercise $exercise, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'sometimes|string',
            'content' => 'sometimes|json',
            'correct_answer' => 'sometimes|json',
            'distractors' => 'nullable|json',
            'order' => 'sometimes|integer|min:1',
            'xp_reward' => 'sometimes|integer|min:0',
            'hints' => 'sometimes|array',
            'hints.*.id' => 'sometimes|exists:exercise_hints,id',
            'hints.*.hint' => 'required_with:hints|string',
            'hints.*.order' => 'required_with:hints|integer|min:0',
            'hints.*.xp_penalty' => 'required_with:hints|integer|min:0',
        ]);

        $exercise = $this->exerciseService->updateExercise($exercise, $validated);

        return response()->json([
            'message' => 'Exercise updated successfully',
            'exercise' => $this->exerciseService->attachStats($exercise)
        ]);
    }

    /**
     * Delete an exercise
     */
    public function destroy(Exercise $exercise): JsonResponse
    {
        // Check if exercise has any completions
        if ($exercise->hasCompletions()) {
            return response()->json([
                'message' => 'Cannot delete exercise with user completions',
                'completion_count' => $exercise->completionCount()
            ], 422);
        }

        $this->exerciseService->deleteExercise($exercise);

        return response()->json([
            'message' => 'Exercise deleted successfully'
        ]);
    }

    /**
     * Clone an exercise
     */
    public function clone(Exercise $exercise): JsonResponse
    {
        $clonedExercise = $this->exerciseService->cloneExercise($exercise);

        return response()->json([
            'message' => 'Exercise cloned successfully',
            'exercise' => $this->exerciseService->attachStats($clonedExercise)
        ]);
    }

    /**
     * List exercise templates for a specific type
     */
    public function listTemplates(ExerciseType $type): JsonResponse
    {
        return response()->json([
            'templates' => Exercise::where('exercise_type_id', $type->id)
                ->where('is_template', true)
                ->get()
                ->map(function ($exercise) {
                    return [
                        'id' => $exercise->id,
                        'name' => $exercise->name,
                        'description' => $exercise->description,
                        'structure' => [
                            'prompt' => $exercise->prompt,
                            'content' => $exercise->content,
                            'correct_answer' => $exercise->correct_answer,
                            'distractors' => $exercise->distractors,
                        ]
                    ];
                })
        ]);
    }
}