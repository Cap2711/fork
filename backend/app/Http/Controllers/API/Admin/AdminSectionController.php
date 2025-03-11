<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Http\Controllers\API\SectionController as BaseSectionController;
use App\Models\Section;
use App\Models\Lesson;
use App\Http\Requests\API\Section\StoreSectionRequest;
use App\Http\Requests\API\Section\UpdateSectionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSectionController extends BaseAPIController
{
    /**
     * The base section controller instance.
     */
    protected $baseSectionController;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->baseSectionController = new BaseSectionController();
    }

    /**
     * Display a listing of all sections for admin.
     * Admins can see all sections including drafts and archived.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Section::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

        // Include relationships if requested
        if ($request->has('with_exercises')) {
            $query->with(['exercises' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_lesson')) {
            $query->with('lesson');
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

        // Log the creation for audit trail
        activity()
            ->performedOn($section)
            ->causedBy($request->user())
            ->withProperties(['data' => $request->validated()])
            ->log('created');

        return $this->sendCreatedResponse($section, 'Section created successfully.');
    }

    /**
     * Display the specified section for admin.
     * Admins can see additional information like version history.
     */
    public function show(Request $request, Section $section): JsonResponse
    {
        // Load relationships if requested
        if ($request->has('with_exercises')) {
            $section->load(['exercises' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_lesson')) {
            $section->load('lesson');
        }

        if ($request->has('with_versions')) {
            // Load version history if requested
            $section->load('versions');
        }

        if ($request->has('with_reviews')) {
            // Load reviews if requested
            $section->load('reviews');
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
     * Admins can delete sections that aren't published.
     */
    public function destroy(Request $request, Section $section): JsonResponse
    {
        // Prevent deletion of published sections
        if ($section->status === 'published') {
            return $this->sendError('Cannot delete a published section. Archive it first.', 422);
        }

        $data = $section->toArray();
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
     * Admin-specific method to change section status.
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
     * Admin-specific method to reorder exercises.
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

    /**
     * Submit a section for review.
     */
    public function submitForReview(Request $request, Section $section): JsonResponse
    {
        // Validate that the section is in draft status
        if ($section->status !== 'draft') {
            return $this->sendError('Only draft sections can be submitted for review.', 422);
        }

        // Update the section status to 'in_review'
        $section->review_status = 'pending';
        $section->save();

        // Create a review record
        $review = $section->reviews()->create([
            'submitted_by' => $request->user()->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Notify reviewers (to be implemented)
        // $this->notifyReviewers($section, $review);

        // Log the review submission
        activity()
            ->performedOn($section)
            ->causedBy($request->user())
            ->withProperties(['review_id' => $review->id])
            ->log('submitted_for_review');

        return $this->sendResponse($section, 'Section submitted for review successfully.');
    }

    /**
     * Approve a section review.
     */
    public function approveReview(Request $request, Section $section): JsonResponse
    {
        // Validate that the section is in review status
        if ($section->review_status !== 'pending') {
            return $this->sendError('This section is not pending review.', 422);
        }

        $request->validate([
            'review_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        // Get the latest pending review
        $review = $section->reviews()->where('status', 'pending')->latest()->first();
        
        if (!$review) {
            return $this->sendError('No pending review found for this section.', 404);
        }

        // Update the review
        $review->update([
            'reviewed_by' => $request->user()->id,
            'status' => 'approved',
            'review_comment' => $request->review_comment,
            'reviewed_at' => now(),
        ]);

        // Update the section status
        $section->review_status = 'approved';
        $section->save();

        // Notify the content creator (to be implemented)
        // $this->notifyContentCreator($section, $review, 'approved');

        // Log the review approval
        activity()
            ->performedOn($section)
            ->causedBy($request->user())
            ->withProperties(['review_id' => $review->id])
            ->log('review_approved');

        return $this->sendResponse($section, 'Section review approved successfully.');
    }

    /**
     * Reject a section review.
     */
    public function rejectReview(Request $request, Section $section): JsonResponse
    {
        // Validate that the section is in review status
        if ($section->review_status !== 'pending') {
            return $this->sendError('This section is not pending review.', 422);
        }

        $request->validate([
            'review_comment' => ['required', 'string', 'max:1000'],
            'rejection_reason' => ['required', 'string', 'in:content_issues,formatting_issues,accuracy_issues,other'],
        ]);

        // Get the latest pending review
        $review = $section->reviews()->where('status', 'pending')->latest()->first();
        
        if (!$review) {
            return $this->sendError('No pending review found for this section.', 404);
        }

        // Update the review
        $review->update([
            'reviewed_by' => $request->user()->id,
            'status' => 'rejected',
            'review_comment' => $request->review_comment,
            'rejection_reason' => $request->rejection_reason,
            'reviewed_at' => now(),
        ]);

        // Update the section status
        $section->review_status = 'rejected';
        $section->save();

        // Notify the content creator (to be implemented)
        // $this->notifyContentCreator($section, $review, 'rejected');

        // Log the review rejection
        activity()
            ->performedOn($section)
            ->causedBy($request->user())
            ->withProperties([
                'review_id' => $review->id,
                'rejection_reason' => $request->rejection_reason
            ])
            ->log('review_rejected');

        return $this->sendResponse($section, 'Section review rejected successfully.');
    }
}
