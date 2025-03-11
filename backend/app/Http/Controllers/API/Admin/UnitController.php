<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Unit;
use App\Models\LearningPath;
use App\Http\Requests\API\Unit\StoreUnitRequest;
use App\Http\Requests\API\Unit\UpdateUnitRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends BaseAPIController
{
    /**
     * Display a listing of all units.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Unit::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('learning_path_id')) {
            $query->whereHas('learningPaths', function ($query) use ($request) {
                $query->where('learning_paths.id', $request->learning_path_id);
            });
        }

        // Include relationships if requested
        if ($request->has('with_lessons')) {
            $query->with(['lessons' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_learning_paths')) {
            $query->with('learningPaths');
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

        // Attach to learning path if specified
        if ($request->has('learning_path_id')) {
            $learningPath = LearningPath::findOrFail($request->learning_path_id);
            
            // Get the highest order in the learning path
            $maxOrder = $learningPath->units()->max('order') ?? 0;
            
            // Attach with the next order
            $learningPath->units()->attach($unit->id, ['order' => $maxOrder + 1]);
        }

        // Log the creation for audit trail
        activity()
            ->performedOn($unit)
            ->causedBy($request->user())
            ->withProperties(['data' => $request->validated()])
            ->log('created');

        return $this->sendCreatedResponse($unit, 'Unit created successfully.');
    }

    /**
     * Display the specified unit.
     */
    public function show(Request $request, Unit $unit): JsonResponse
    {
        // Load relationships if requested
        if ($request->has('with_lessons')) {
            $unit->load(['lessons' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_learning_paths')) {
            $unit->load('learningPaths');
        }

        if ($request->has('with_versions')) {
            // Load version history if requested
            $unit->load('versions');
        }

        return $this->sendResponse($unit);
    }

    /**
     * Update the specified unit.
     */
    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $oldData = $unit->toArray();
        $unit->update($request->validated());

        // Log the update for audit trail
        activity()
            ->performedOn($unit)
            ->causedBy($request->user())
            ->withProperties([
                'old' => $oldData,
                'new' => $request->validated()
            ])
            ->log('updated');

        return $this->sendResponse($unit, 'Unit updated successfully.');
    }

    /**
     * Remove the specified unit.
     */
    public function destroy(Request $request, Unit $unit): JsonResponse
    {
        // Prevent deletion of published units
        if ($unit->status === 'published') {
            return $this->sendError('Cannot delete a published unit. Archive it first.', 422);
        }

        $data = $unit->toArray();
        
        // Detach from all learning paths
        $unit->learningPaths()->detach();
        
        $unit->delete();

        // Log the deletion for audit trail
        activity()
            ->performedOn($unit)
            ->causedBy($request->user())
            ->withProperties(['data' => $data])
            ->log('deleted');

        return $this->sendNoContentResponse();
    }

    /**
     * Update the status of a unit.
     */
    public function updateStatus(Request $request, Unit $unit): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:draft,published,archived']
        ]);

        $oldStatus = $unit->status;
        $unit->status = $request->status;
        $unit->save();

        // Log the status change for audit trail
        activity()
            ->performedOn($unit)
            ->causedBy($request->user())
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $request->status
            ])
            ->log('status_updated');

        return $this->sendResponse($unit, 'Unit status updated successfully.');
    }

    /**
     * Reorder lessons within a unit.
     */
    public function reorderLessons(Request $request, Unit $unit): JsonResponse
    {
        $request->validate([
            'lesson_ids' => ['required', 'array'],
            'lesson_ids.*' => ['exists:lessons,id']
        ]);

        $lessonIds = $request->lesson_ids;
        
        // Update the order of each lesson
        foreach ($lessonIds as $index => $lessonId) {
            $unit->lessons()->updateExistingPivot($lessonId, ['order' => $index + 1]);
        }

        return $this->sendResponse($unit->load('lessons'), 'Lessons reordered successfully.');
    }
}
