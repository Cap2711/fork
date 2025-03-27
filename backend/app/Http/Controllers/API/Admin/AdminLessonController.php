<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Http\Controllers\API\LessonController as BaseLessonController;
use App\Models\Lesson;
use App\Models\Unit;
use App\Http\Requests\API\Lesson\StoreLessonRequest;
use App\Http\Requests\API\Lesson\UpdateLessonRequest;
use App\Models\AuditLog;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdminLessonController extends BaseAPIController
{
    /**
     * The base lesson controller instance.
     */
    protected $baseLessonController;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->baseLessonController = new BaseLessonController();
    }

    /**
     * Display a listing of all lessons for admin.
     * Admins can see all lessons including drafts and archived.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Lesson::query();

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('unit_id')) {
                $query->whereHas('units', function ($query) use ($request) {
                    $query->where('units.id', $request->unit_id);
                });
            }

            // Include relationships if requested
            if ($request->has('with_sections')) {
                $query->with(['sections' => function ($query) {
                    $query->orderBy('order');
                }]);
            }

            if ($request->has('with_units')) {
                $query->with('units');
            }

            $perPage = $request->input('per_page', 15);
            $lessons = $query->paginate($perPage);

            return $this->sendPaginatedResponse($lessons);
        } catch (Exception $e) {
            Log::error('Error fetching lessons: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve lessons', [], 500);
        }
    }

    /**
     * Store a newly created lesson.
     */
    public function store(StoreLessonRequest $request): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $lesson = Lesson::create($request->validated());

                // Attach to unit if specified
                if ($request->has('unit_id')) {
                    try {
                        $unit = Unit::findOrFail($request->unit_id);
                        
                        // Get the highest order in the unit
                        $maxOrder = $unit->lessons()->max('order') ?? 0;
                        
                        // Attach with the next order
                        $unit->lessons()->attach($lesson->id, ['order' => $maxOrder + 1]);
                    } catch (ModelNotFoundException $e) {
                        throw new Exception('Unit not found', 404);
                    }
                }

                // Log the creation for audit trail
                AuditLog::log(
                    'create',
                    'lessons',
                    $lesson,
                    [],
                    $request->validated()
                );

                return $this->sendCreatedResponse($lesson, 'Lesson created successfully.');
            });
        } catch (Exception $e) {
            Log::error('Error creating lesson: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return $this->sendError('Failed to create lesson: ' . $e->getMessage(), [], $statusCode);
        }
    }

    /**
     * Display the specified lesson for admin.
     * Admins can see additional information like version history.
     */
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        try {
            // Load relationships if requested
            if ($request->has('with_sections')) {
                $lesson->load(['sections' => function ($query) {
                    $query->orderBy('order');
                }]);
            }

            if ($request->has('with_units')) {
                $lesson->load('units');
            }

            if ($request->has('with_versions')) {
                // Load version history if requested
                $lesson->load('versions');
            }

            return $this->sendResponse($lesson);
        } catch (Exception $e) {
            Log::error('Error fetching lesson: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to retrieve lesson details', [], 500);
        }
    }

    /**
     * Update the specified lesson.
     */
    public function update(UpdateLessonRequest $request, Lesson $lesson): JsonResponse
    {
        try {
            $oldData = $lesson->toArray();
            $lesson->update($request->validated());

            // Log the update for audit trail
            AuditLog::logChange(
                $lesson,
                'update',
                $oldData,
                $lesson->toArray()
            );

            return $this->sendResponse($lesson, 'Lesson updated successfully.');
        } catch (Exception $e) {
            Log::error('Error updating lesson: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to update lesson: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Remove the specified lesson.
     * Admins can delete lessons that aren't published.
     */
    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        try {
            // Prevent deletion of published lessons
            if ($lesson->status === 'published') {
                return $this->sendError('Cannot delete a published lesson. Archive it first.', [], 422);
            }

            $data = $lesson->toArray();
            
            DB::transaction(function () use ($lesson, $data) {
                // Detach from all units
                $lesson->units()->detach();
                
                $lesson->delete();

                // Log the deletion for audit trail
                AuditLog::log(
                    'delete',
                    'lessons',
                    $lesson,
                    $data,
                    []
                );
            });

            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            Log::error('Error deleting lesson: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to delete lesson: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update the status of a lesson.
     * Admin-specific method to change lesson status.
     */
    public function updateStatus(Request $request, Lesson $lesson): JsonResponse
    {
        try {
            $request->validate([
                'status' => ['required', 'string', 'in:draft,published,archived']
            ]);

            $oldStatus = $lesson->status;
            $lesson->status = $request->status;
            $lesson->save();

            // Log the status change for audit trail
            AuditLog::log(
                'status_update',
                'lessons',
                $lesson,
                ['status' => $oldStatus],
                ['status' => $request->status]
            );

            return $this->sendResponse($lesson, 'Lesson status updated successfully.');
        } catch (Exception $e) {
            Log::error('Error updating lesson status: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to update lesson status: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Reorder sections within a lesson.
     * Admin-specific method to reorder sections.
     */
    public function reorderSections(Request $request, Lesson $lesson): JsonResponse
    {
        try {
            $request->validate([
                'section_ids' => ['required', 'array'],
                'section_ids.*' => ['exists:sections,id']
            ]);

            $sectionIds = $request->section_ids;
            
            DB::transaction(function () use ($lesson, $sectionIds) {
                // Update the order of each section
                foreach ($sectionIds as $index => $sectionId) {
                    $lesson->sections()->updateExistingPivot($sectionId, ['order' => $index + 1]);
                }
            });
            
            return $this->sendResponse($lesson->load('sections'), 'Sections reordered successfully.');
        } catch (Exception $e) {
            Log::error('Error reordering sections: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to reorder sections: ' . $e->getMessage(), [], 500);
        }
    }
}
