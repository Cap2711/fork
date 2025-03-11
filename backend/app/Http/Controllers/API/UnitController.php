<?php

namespace App\Http\Controllers\API;

use App\Models\Unit;
use App\Models\LearningPath;
use App\Http\Requests\API\Unit\StoreUnitRequest;
use App\Http\Requests\API\Unit\UpdateUnitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnitController extends BaseAPIController
{
    /**
     * Display a listing of units for a learning path.
     */
    public function index(Request $request, LearningPath $learningPath): JsonResponse
    {
        $query = $learningPath->units()->orderBy('order');

        // Include relationships if requested
        if ($request->has('with_lessons')) {
            $query->with(['lessons' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_quizzes')) {
            $query->with('quizzes');
        }

        if ($request->has('with_guide')) {
            $query->with('guideBookEntries');
        }

        $perPage = $request->input('per_page', 15);
        $units = $query->paginate($perPage);

        return $this->sendPaginatedResponse($units);
    }

    /**
     * Store a newly created unit.
     */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = Unit::create($request->validated());

        if ($request->has('with_relationships')) {
            $unit->load(['lessons', 'quizzes', 'guideBookEntries']);
        }

        return $this->sendCreatedResponse($unit, 'Unit created successfully.');
    }

    /**
     * Display the specified unit.
     */
    public function show(Request $request, Unit $unit): JsonResponse
    {
        if ($request->has('with_lessons')) {
            $unit->load(['lessons' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_quizzes')) {
            $unit->load('quizzes');
        }

        if ($request->has('with_guide')) {
            $unit->load('guideBookEntries');
        }

        if ($request->has('with_progress') && $request->user()) {
            $unit->load(['progress' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        }

        return $this->sendResponse($unit);
    }

    /**
     * Update the specified unit.
     */
    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $unit->update($request->validated());

        if ($request->has('with_relationships')) {
            $unit->load(['lessons', 'quizzes', 'guideBookEntries']);
        }

        return $this->sendResponse($unit, 'Unit updated successfully.');
    }

    /**
     * Remove the specified unit.
     */
    public function destroy(Unit $unit): JsonResponse
    {
        // Check if learning path is published
        if ($unit->learningPath->status === 'published') {
            return $this->sendError('Cannot delete a unit from a published learning path.');
        }

        // Reorder remaining units
        Unit::where('learning_path_id', $unit->learning_path_id)
            ->where('order', '>', $unit->order)
            ->decrement('order');

        $unit->delete();

        return $this->sendNoContentResponse();
    }

    /**
     * Update the order of units.
     */
    public function reorder(Request $request, LearningPath $learningPath): JsonResponse
    {
        $request->validate([
            'units' => ['required', 'array'],
            'units.*' => ['required', 'integer', 'distinct'],
        ]);

        $unitIds = $request->units;
        $order = 1;

        // Verify all units belong to the learning path
        $units = Unit::whereIn('id', $unitIds)
            ->where('learning_path_id', $learningPath->id)
            ->get();

        if ($units->count() !== count($unitIds)) {
            return $this->sendError('Invalid unit IDs provided.');
        }

        // Update order
        foreach ($unitIds as $unitId) {
            Unit::where('id', $unitId)->update(['order' => $order++]);
        }

        return $this->sendResponse(
            $learningPath->units()->orderBy('order')->get(),
            'Units reordered successfully.'
        );
    }

    /**
     * Get unit progress for the authenticated user.
     */
    public function progress(Request $request, Unit $unit): JsonResponse
    {
        $progress = $unit->progress()
            ->where('user_id', $request->user()->id)
            ->first();

        $lessonsProgress = $unit->lessons()
            ->with(['progress' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->get()
            ->map(function ($lesson) {
                $progress = $lesson->progress->first();
                return [
                    'lesson_id' => $lesson->id,
                    'status' => $progress ? $progress->status : 'not_started'
                ];
            });

        return $this->sendResponse([
            'unit_progress' => $progress ? $progress->status : 'not_started',
            'completion_percentage' => $unit->getCompletionPercentage($request->user()->id),
            'lessons_progress' => $lessonsProgress
        ]);
    }
}