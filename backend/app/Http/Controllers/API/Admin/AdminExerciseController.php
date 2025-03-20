<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Exercise;
use App\Models\Section;
use App\Http\Requests\API\Exercise\StoreExerciseRequest;
use App\Http\Requests\API\Exercise\UpdateExerciseRequest;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AdminExerciseController extends BaseAPIController
{
    /**
     * Display a listing of all exercises.
     */
    public function index(Request $request): JsonResponse
    {
        try {
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
        } catch (Exception $e) {
            Log::error('Error fetching exercises: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve exercises', ['status' => 500]);
        }
    }

    /**
     * Store a newly created exercise.
     */
    public function store(StoreExerciseRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
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
            AuditLog::log(
                'create',
                'exercises',
                $exercise,
                [],
                $request->validated()
            );

            DB::commit();
            return $this->sendCreatedResponse($exercise, 'Exercise created successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating exercise: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to create exercise: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Display the specified exercise.
     */
    public function show(Request $request, Exercise $exercise): JsonResponse
    {
        try {
            // Load relationships if requested
            if ($request->has('with_sections')) {
                $exercise->load('sections');
            }

            if ($request->has('with_versions')) {
                // Load version history if requested
                $exercise->load('versions');
            }

            return $this->sendResponse($exercise);
        } catch (Exception $e) {
            Log::error('Error fetching exercise details: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'exercise_id' => $exercise->id
            ]);
            return $this->sendError('Failed to retrieve exercise details', ['status' => 500]);
        }
    }

    /**
     * Update the specified exercise.
     */
    public function update(UpdateExerciseRequest $request, Exercise $exercise): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Store old data for audit trail
            $oldData = $exercise->toArray();

            // Update the exercise
            $exercise->update($request->validated());

            // Handle section attachment/detachment if specified
            if ($request->has('section_id')) {
                // Detach from all current sections
                $exercise->sections()->detach();
                
                // Attach to new section
                $section = Section::findOrFail($request->section_id);
                
                // Get the highest order in the section
                $maxOrder = $section->exercises()->max('order') ?? 0;
                
                // Attach with the next order
                $section->exercises()->attach($exercise->id, ['order' => $maxOrder + 1]);
            }

            // Log the update for audit trail
            AuditLog::logChange(
                $exercise,
                'update',
                $oldData,
                $exercise->toArray()
            );

            DB::commit();
            return $this->sendResponse($exercise, 'Exercise updated successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating exercise: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'exercise_id' => $exercise->id
            ]);
            return $this->sendError('Failed to update exercise: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Remove the specified exercise.
     */
    public function destroy(Request $request, Exercise $exercise): JsonResponse
    {
        DB::beginTransaction();
        try {
            // Store data for audit trail
            $data = $exercise->toArray();

            // Delete the exercise
            $exercise->delete();

            // Log the deletion for audit trail
            AuditLog::log(
                'delete',
                'exercises',
                $exercise,
                $data,
                []
            );

            DB::commit();
            return $this->sendResponse(null, 'Exercise deleted successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deleting exercise: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'exercise_id' => $exercise->id
            ]);
            return $this->sendError('Failed to delete exercise: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Update the status of an exercise.
     */
    public function updateStatus(Request $request, Exercise $exercise): JsonResponse
    {
        DB::beginTransaction();
        try {
            $request->validate([
                'status' => 'required|string|in:draft,published,archived'
            ]);

            $oldStatus = $exercise->status;
            $exercise->status = $request->status;
            $exercise->save();

            // Log the status change for audit trail
            AuditLog::log(
                'status_update',
                'exercises',
                $exercise,
                ['status' => $oldStatus],
                ['status' => $request->status]
            );

            DB::commit();
            return $this->sendResponse($exercise, 'Exercise status updated successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating exercise status: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'exercise_id' => $exercise->id
            ]);
            return $this->sendError('Failed to update exercise status: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Clone an existing exercise.
     */
    public function clone(Request $request, Exercise $exercise): JsonResponse
    {
        DB::beginTransaction();
        try {
            $newExercise = $exercise->replicate();
            $newExercise->title = $newExercise->title . ' (Copy)';
            $newExercise->status = 'draft';
            $newExercise->save();

            // Log the cloning for audit trail
            AuditLog::log(
                'clone',
                'exercises',
                $newExercise,
                [],
                ['original_id' => $exercise->id]
            );

            DB::commit();
            return $this->sendCreatedResponse($newExercise, 'Exercise cloned successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error cloning exercise: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'original_exercise_id' => $exercise->id
            ]);
            return $this->sendError('Failed to clone exercise: ' . $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Reorder exercises within a section.
     */
    public function reorder(Request $request, Section $section): JsonResponse
    {
        // Begin a transaction
        DB::beginTransaction();

        try {
            $request->validate([
                'exercises' => 'required|array',
                'exercises.*.id' => 'required|exists:exercises,id',
                'exercises.*.order' => 'required|integer|min:0'
            ]);

            foreach ($request->exercises as $item) {
                // Update the pivot table with the new order
                $section->exercises()->updateExistingPivot($item['id'], ['order' => $item['order']]);
            }

            // Log the reordering
            AuditLog::log(
                'reorder',
                'exercises',
                $section,
                [],
                [],
                [
                    'metadata' => [
                        'section_id' => $section->id,
                        'exercises' => $request->exercises
                    ]
                ]
            );

            // Commit the transaction
            DB::commit();
            return $this->sendResponse(null, 'Exercises reordered successfully.');
        } catch (Exception $e) {
            // Rollback the transaction in case of error
            DB::rollBack();
            Log::error('Error reordering exercises: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'section_id' => $section->id
            ]);
            return $this->sendError('Failed to reorder exercises: ' . $e->getMessage(), ['status' => 500]);
        }
    }
}
