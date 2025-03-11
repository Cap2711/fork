<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Exercise;
use App\Models\Section;
use App\Http\Requests\API\Exercise\StoreExerciseRequest;
use App\Http\Requests\API\Exercise\UpdateExerciseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciseController extends BaseAPIController
{
    /**
     * Display a listing of all exercises.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Exercise::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('section_id')) {
            $query->whereHas('sections', function ($query) use ($request) {
                $query->where('sections.id', $request->section_id);
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        // Include relationships if requested
        if ($request->has('with_sections')) {
            $query->with('sections');
        }

        $perPage = $request->input('per_page', 15);
        $exercises = $query->paginate($perPage);

        return $this->sendPaginatedResponse($exercises);
    }

    /**
     * Store a newly created exercise.
     */
    public function store(StoreExerciseRequest $request): JsonResponse
    {
        $exercise = Exercise::create($request->validated());

        // Attach to section if specified
        if ($request->has('section_id')) {
            $section = Section::findOrFail($request->section_id);
            
            // Get the highest order in the section
            $maxOrder = $section->exercises()->max('order') ?? 0;
            
            // Attach with the next order
            $section->exercises()->attach($exercise->id, ['order' => $maxOrder + 1]);
        }

        // Log the creation for audit trail
        activity()
            ->performedOn($exercise)
            ->causedBy($request->user())
            ->withProperties(['data' => $request->validated()])
            ->log('created');

        return $this->sendCreatedResponse($exercise, 'Exercise created successfully.');
    }

    /**
     * Display the specified exercise.
     */
    public function show(Request $request, Exercise $exercise): JsonResponse
    {
        // Load relationships if requested
        if ($request->has('with_sections')) {
            $exercise->load('sections');
        }

        if ($request->has('with_versions')) {
            // Load version history if requested
            $exercise->load('versions');
        }

        return $this->sendResponse($exercise);
    }

    /**
     * Update the specified exercise.
     */
    public function update(UpdateExerciseRequest $request, Exercise $exercise): JsonResponse
    {
        $oldData = $exercise->toArray();
        $exercise->update($request->validated());

        // Log the update for audit trail
        activity()
            ->performedOn($exercise)
            ->causedBy($request->user())
            ->withProperties([
                'old' => $oldData,
                'new' => $request->validated()
            ])
            ->log('updated');

        return $this->sendResponse($exercise, 'Exercise updated successfully.');
    }

    /**
     * Remove the specified exercise.
     */
    public function destroy(Request $request, Exercise $exercise): JsonResponse
    {
        // Prevent deletion of published exercises
        if ($exercise->status === 'published') {
            return $this->sendError('Cannot delete a published exercise. Archive it first.', 422);
        }

        $data = $exercise->toArray();
        
        // Detach from all sections
        $exercise->sections()->detach();
        
        $exercise->delete();

        // Log the deletion for audit trail
        activity()
            ->performedOn($exercise)
            ->causedBy($request->user())
            ->withProperties(['data' => $data])
            ->log('deleted');

        return $this->sendNoContentResponse();
    }

    /**
     * Update the status of an exercise.
     */
    public function updateStatus(Request $request, Exercise $exercise): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:draft,published,archived']
        ]);

        $oldStatus = $exercise->status;
        $exercise->status = $request->status;
        $exercise->save();

        // Log the status change for audit trail
        activity()
            ->performedOn($exercise)
            ->causedBy($request->user())
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $request->status
            ])
            ->log('status_updated');

        return $this->sendResponse($exercise, 'Exercise status updated successfully.');
    }

    /**
     * Clone an exercise.
     */
    public function clone(Request $request, Exercise $exercise): JsonResponse
    {
        // Create a new exercise with the same data
        $newExercise = $exercise->replicate();
        $newExercise->title = $exercise->title . ' (Copy)';
        $newExercise->status = 'draft';
        $newExercise->save();

        // Log the cloning for audit trail
        activity()
            ->performedOn($newExercise)
            ->causedBy($request->user())
            ->withProperties([
                'original_id' => $exercise->id,
                'data' => $newExercise->toArray()
            ])
            ->log('cloned');

        return $this->sendCreatedResponse($newExercise, 'Exercise cloned successfully.');
    }
}
