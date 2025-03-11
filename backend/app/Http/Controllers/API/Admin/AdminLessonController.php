<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Http\Controllers\API\LessonController as BaseLessonController;
use App\Models\Lesson;
use App\Models\Unit;
use App\Http\Requests\API\Lesson\StoreLessonRequest;
use App\Http\Requests\API\Lesson\UpdateLessonRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    }

    /**
     * Store a newly created lesson.
     */
    public function store(StoreLessonRequest $request): JsonResponse
    {
        $lesson = Lesson::create($request->validated());

        // Attach to unit if specified
        if ($request->has('unit_id')) {
            $unit = Unit::findOrFail($request->unit_id);
            
            // Get the highest order in the unit
            $maxOrder = $unit->lessons()->max('order') ?? 0;
            
            // Attach with the next order
            $unit->lessons()->attach($lesson->id, ['order' => $maxOrder + 1]);
        }

        // Log the creation for audit trail
        activity()
            ->performedOn($lesson)
            ->causedBy($request->user())
            ->withProperties(['data' => $request->validated()])
            ->log('created');

        return $this->sendCreatedResponse($lesson, 'Lesson created successfully.');
    }

    /**
     * Display the specified lesson for admin.
     * Admins can see additional information like version history.
     */
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
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
    }

    /**
     * Update the specified lesson.
     */
    public function update(UpdateLessonRequest $request, Lesson $lesson): JsonResponse
    {
        $oldData = $lesson->toArray();
        $lesson->update($request->validated());

        // Log the update for audit trail
        activity()
            ->performedOn($lesson)
            ->causedBy($request->user())
            ->withProperties([
                'old' => $oldData,
                'new' => $request->validated()
            ])
            ->log('updated');

        return $this->sendResponse($lesson, 'Lesson updated successfully.');
    }

    /**
     * Remove the specified lesson.
     * Admins can delete lessons that aren't published.
     */
    public function destroy(Request $request, Lesson $lesson): JsonResponse
    {
        // Prevent deletion of published lessons
        if ($lesson->status === 'published') {
            return $this->sendError('Cannot delete a published lesson. Archive it first.', 422);
        }

        $data = $lesson->toArray();
        
        // Detach from all units
        $lesson->units()->detach();
        
        $lesson->delete();

        // Log the deletion for audit trail
        activity()
            ->performedOn($lesson)
            ->causedBy($request->user())
            ->withProperties(['data' => $data])
            ->log('deleted');

        return $this->sendNoContentResponse();
    }

    /**
     * Update the status of a lesson.
     * Admin-specific method to change lesson status.
     */
    public function updateStatus(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:draft,published,archived']
        ]);

        $oldStatus = $lesson->status;
        $lesson->status = $request->status;
        $lesson->save();

        // Log the status change for audit trail
        activity()
            ->performedOn($lesson)
            ->causedBy($request->user())
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $request->status
            ])
            ->log('status_updated');

        return $this->sendResponse($lesson, 'Lesson status updated successfully.');
    }

    /**
     * Reorder sections within a lesson.
     * Admin-specific method to reorder sections.
     */
    public function reorderSections(Request $request, Lesson $lesson): JsonResponse
    {
        $request->validate([
            'section_ids' => ['required', 'array'],
            'section_ids.*' => ['exists:sections,id']
        ]);

        $sectionIds = $request->section_ids;
        
        // Update the order of each section
        foreach ($sectionIds as $index => $sectionId) {
            $lesson->sections()->updateExistingPivot($sectionId, ['order' => $index + 1]);
        }

        return $this->sendResponse($lesson->load('sections'), 'Sections reordered successfully.');
    }
}
