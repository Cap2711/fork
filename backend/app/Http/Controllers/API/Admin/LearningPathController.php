<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\LearningPath;
use App\Http\Requests\API\LearningPath\StoreLearningPathRequest;
use App\Http\Requests\API\LearningPath\UpdateLearningPathRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningPathController extends BaseAPIController
{
    /**
     * Display a listing of all learning paths (including drafts and archived).
     */
    public function index(Request $request): JsonResponse
    {
        $query = LearningPath::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('target_level')) {
            $query->where('target_level', $request->target_level);
        }

        // Include relationships if requested
        if ($request->has('with_units')) {
            $query->with(['units' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        $perPage = $request->input('per_page', 15);
        $learningPaths = $query->paginate($perPage);

        return $this->sendPaginatedResponse($learningPaths);
    }

    /**
     * Store a newly created learning path.
     */
    public function store(StoreLearningPathRequest $request): JsonResponse
    {
        $learningPath = LearningPath::create($request->validated());

        // Log the creation for audit trail
        activity()
            ->performedOn($learningPath)
            ->causedBy($request->user())
            ->withProperties(['data' => $request->validated()])
            ->log('created');

        return $this->sendCreatedResponse($learningPath, 'Learning path created successfully.');
    }

    /**
     * Display the specified learning path.
     */
    public function show(Request $request, LearningPath $learningPath): JsonResponse
    {
        // Load relationships if requested
        if ($request->has('with_units')) {
            $learningPath->load(['units' => function ($query) {
                $query->orderBy('order')->with(['lessons' => function ($query) {
                    $query->orderBy('order');
                }]);
            }]);
        }

        if ($request->has('with_versions')) {
            // Load version history if requested
            $learningPath->load('versions');
        }

        return $this->sendResponse($learningPath);
    }

    /**
     * Update the specified learning path.
     */
    public function update(UpdateLearningPathRequest $request, LearningPath $learningPath): JsonResponse
    {
        $oldData = $learningPath->toArray();
        $learningPath->update($request->validated());

        // Log the update for audit trail
        activity()
            ->performedOn($learningPath)
            ->causedBy($request->user())
            ->withProperties([
                'old' => $oldData,
                'new' => $request->validated()
            ])
            ->log('updated');

        return $this->sendResponse($learningPath, 'Learning path updated successfully.');
    }

    /**
     * Remove the specified learning path.
     */
    public function destroy(Request $request, LearningPath $learningPath): JsonResponse
    {
        // Prevent deletion of published learning paths
        if ($learningPath->status === 'published') {
            return $this->sendError('Cannot delete a published learning path. Archive it first.', 422);
        }

        $data = $learningPath->toArray();
        $learningPath->delete();

        // Log the deletion for audit trail
        activity()
            ->performedOn($learningPath)
            ->causedBy($request->user())
            ->withProperties(['data' => $data])
            ->log('deleted');

        return $this->sendNoContentResponse();
    }

    /**
     * Update the status of a learning path.
     */
    public function updateStatus(Request $request, LearningPath $learningPath): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:draft,published,archived']
        ]);

        $oldStatus = $learningPath->status;
        $learningPath->status = $request->status;
        $learningPath->save();

        // Log the status change for audit trail
        activity()
            ->performedOn($learningPath)
            ->causedBy($request->user())
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $request->status
            ])
            ->log('status_updated');

        return $this->sendResponse($learningPath, 'Learning path status updated successfully.');
    }

    /**
     * Reorder units within a learning path.
     */
    public function reorderUnits(Request $request, LearningPath $learningPath): JsonResponse
    {
        $request->validate([
            'unit_ids' => ['required', 'array'],
            'unit_ids.*' => ['exists:units,id']
        ]);

        $unitIds = $request->unit_ids;
        
        // Update the order of each unit
        foreach ($unitIds as $index => $unitId) {
            $learningPath->units()->updateExistingPivot($unitId, ['order' => $index + 1]);
        }

        return $this->sendResponse($learningPath->load('units'), 'Units reordered successfully.');
    }
}
