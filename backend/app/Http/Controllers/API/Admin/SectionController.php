<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Section;
use App\Models\Lesson;
use App\Http\Requests\API\Section\StoreSectionRequest;
use App\Http\Requests\API\Section\UpdateSectionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends BaseAPIController
{
    /**
     * Display a listing of all sections.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Section::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('lesson_id')) {
            $query->whereHas('lessons', function ($query) use ($request) {
                $query->where('lessons.id', $request->lesson_id);
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Include relationships if requested
        if ($request->has('with_exercises')) {
            $query->with(['exercises' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_lessons')) {
            $query->with('lessons');
        }

        $perPage = $request->input('per_page', 15);
        $sections = $query->paginate($perPage);

        return $this->sendPaginatedResponse($sections);
    }

    /**
     * Store a newly created section.
     */
    public function store(StoreSectionRequest $request): JsonResponse
    {
        $section = Section::create($request->validated());

        // Attach to lesson if specified
        if ($request->has('lesson_id')) {
            $lesson = Lesson::findOrFail($request->lesson_id);
            
            // Get the highest order in the lesson
            $maxOrder = $lesson->sections()->max('order') ?? 0;
            
            // Attach with the next order
            $lesson->sections()->attach($section->id, ['order' => $maxOrder + 1]);
        }

        // Log the creation for audit trail
        activity()
            ->performedOn($section)
            ->causedBy($request->user())
            ->withProperties(['data' => $request->validated()])
            ->log('created');

        return $this->sendCreatedResponse($section, 'Section created successfully.');
    }

    /**
     * Display the specified section.
     */
    public function show(Request $request, Section $section): JsonResponse
    {
        // Load relationships if requested
        if ($request->has('with_exercises')) {
            $section->load(['exercises' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_lessons')) {
            $section->load('lessons');
        }

        if ($request->has('with_versions')) {
            // Load version history if requested
            $section->load('versions');
        }

        return $this->sendResponse($section);
    }

    /**
     * Update the specified section.
     */
    public function update(UpdateSectionRequest $request, Section $section): JsonResponse
    {
        $oldData = $section->toArray();
        $section->update($request->validated());

        // Log the update for audit trail
        activity()
            ->performedOn($section)
            ->causedBy($request->user())
            ->withProperties([
                'old' => $oldData,
                'new' => $request->validated()
            ])
            ->log('updated');

        return $this->sendResponse($section, 'Section updated successfully.');
    }

    /**
     * Remove the specified section.
     */
    public function destroy(Request $request, Section $section): JsonResponse
    {
        // Prevent deletion of published sections
        if ($section->status === 'published') {
            return $this->sendError('Cannot delete a published section. Archive it first.', 422);
        }

        $data = $section->toArray();
        
        // Detach from all lessons
        $section->lessons()->detach();
        
        $section->delete();

        // Log the deletion for audit trail
        activity()
            ->performedOn($section)
            ->causedBy($request->user())
            ->withProperties(['data' => $data])
            ->log('deleted');

        return $this->sendNoContentResponse();
    }

    /**
     * Update the status of a section.
     */
    public function updateStatus(Request $request, Section $section): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:draft,published,archived']
        ]);

        $oldStatus = $section->status;
        $section->status = $request->status;
        $section->save();

        // Log the status change for audit trail
        activity()
            ->performedOn($section)
            ->causedBy($request->user())
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $request->status
            ])
            ->log('status_updated');

        return $this->sendResponse($section, 'Section status updated successfully.');
    }

    /**
     * Reorder exercises within a section.
     */
    public function reorderExercises(Request $request, Section $section): JsonResponse
    {
        $request->validate([
            'exercise_ids' => ['required', 'array'],
            'exercise_ids.*' => ['exists:exercises,id']
        ]);

        $exerciseIds = $request->exercise_ids;
        
        // Update the order of each exercise
        foreach ($exerciseIds as $index => $exerciseId) {
            $section->exercises()->updateExistingPivot($exerciseId, ['order' => $index + 1]);
        }

        return $this->sendResponse($section->load('exercises'), 'Exercises reordered successfully.');
    }
}
