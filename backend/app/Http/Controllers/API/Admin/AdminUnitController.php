<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Http\Controllers\API\UnitController as BaseUnitController;
use App\Models\Unit;
use App\Models\LearningPath;
use App\Http\Requests\API\Unit\StoreUnitRequest;
use App\Http\Requests\API\Unit\UpdateUnitRequest;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUnitController extends BaseAPIController
{
    /**
     * The base unit controller instance.
     */
    protected $baseUnitController;

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->baseUnitController = new BaseUnitController();
    }

    /**
     * Display a listing of all units for admin.
     * Admins can see all units including drafts and archived.
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
        AuditLog::log(
            'create',
            'units',
            $unit,
            [],
            $request->validated()
        );

        return $this->sendCreatedResponse($unit, 'Unit created successfully.');
    }

    /**
     * Display the specified unit for admin.
     * Admins can see additional information like version history.
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

        if ($request->has('with_reviews')) {
            // Load reviews if requested
            $unit->load('reviews');
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
        AuditLog::logChange(
            $unit,
            'update',
            $oldData,
            $unit->toArray()
        );

        return $this->sendResponse($unit, 'Unit updated successfully.');
    }

    /**
     * Remove the specified unit.
     * Admins can delete units that aren't published.
     */
    public function destroy(Request $request, Unit $unit): JsonResponse
    {
        // Prevent deletion of published units
        if ($unit->status === 'published') {
            return $this->sendError('Cannot delete a published unit. Archive it first.', ['status' => 422]);
        }

        $data = $unit->toArray();
        
        // Detach from all learning paths
        $unit->learningPaths()->detach();
        
        $unit->delete();

        // Log the deletion for audit trail
        AuditLog::log(
            'delete',
            'units',
            $unit,
            $data,
            []
        );

        return $this->sendNoContentResponse();
    }

    /**
     * Update the status of a unit.
     * Admin-specific method to change unit status.
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
        AuditLog::log(
            'status_update',
            'units',
            $unit,
            ['status' => $oldStatus],
            ['status' => $request->status]
        );

        return $this->sendResponse($unit, 'Unit status updated successfully.');
    }

    /**
     * Reorder lessons within a unit.
     * Admin-specific method to reorder lessons.
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

    /**
     * Submit a unit for review.
     */
    public function submitForReview(Request $request, Unit $unit): JsonResponse
    {
        // Validate that the unit is in draft status
        if ($unit->status !== 'draft') {
            return $this->sendError('Only draft units can be submitted for review.', ['error' => 'Only draft units can be submitted for review.'], 422);
        }

        // Update the unit status to 'in_review'
        $unit->review_status = 'pending';
        $unit->save();

        // Create a review record
        $review = $unit->reviews()->create([
            'submitted_by' => $request->user()->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Notify reviewers (to be implemented)
        // $this->notifyReviewers($unit, $review);

        // Log the review submission
        AuditLog::log(
            'submit_for_review',
            'units',
            $unit,
            [],
            ['review_id' => $review->id]
        );

        return $this->sendResponse($unit, 'Unit submitted for review successfully.');
    }

    /**
     * Approve a unit review.
     */
    public function approveReview(Request $request, Unit $unit): JsonResponse
    {
        // Validate that the unit is in review status
        if ($unit->review_status !== 'pending') {
            return $this->sendError('This unit is not pending review.', ['error' => 'This unit is not pending review.'], 422);
        }

        $request->validate([
            'review_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        // Get the latest pending review
        $review = $unit->reviews()->where('status', 'pending')->latest()->first();
        
        if (!$review) {
            return $this->sendError('No pending review found for this unit.', ['error' => 'No pending review found for this unit.'], 404);
        }

        // Update the review
        $review->update([
            'reviewed_by' => $request->user()->id,
            'status' => 'approved',
            'review_comment' => $request->review_comment,
            'reviewed_at' => now(),
        ]);

        // Update the unit status
        $unit->review_status = 'approved';
        $unit->save();

        // Notify the content creator (to be implemented)
        // $this->notifyContentCreator($unit, $review, 'approved');

        // Log the review approval
        AuditLog::log(
            'review_approved',
            'units',
            $unit,
            [],
            ['review_id' => $review->id]
        );

        return $this->sendResponse($unit, 'Unit review approved successfully.');
    }

    /**
     * Reject a unit review.
     */
    public function rejectReview(Request $request, Unit $unit): JsonResponse
    {
        // Validate that the unit is in review status
        if ($unit->review_status !== 'pending') {
            return $this->sendError('This unit is not pending review.', ['error' => 'This unit is not pending review.'], 422);
        }

        $request->validate([
            'review_comment' => ['required', 'string', 'max:1000'],
            'rejection_reason' => ['required', 'string', 'in:content_issues,formatting_issues,accuracy_issues,other'],
        ]);

        // Get the latest pending review
        $review = $unit->reviews()->where('status', 'pending')->latest()->first();
        
        if (!$review) {
            return $this->sendError('No pending review found for this unit.', ['error' => 'No pending review found for this unit.'], 404);
        }

        // Update the review
        $review->update([
            'reviewed_by' => $request->user()->id,
            'status' => 'rejected',
            'review_comment' => $request->review_comment,
            'rejection_reason' => $request->rejection_reason,
            'reviewed_at' => now(),
        ]);

        // Update the unit status
        $unit->review_status = 'rejected';
        $unit->save();

        // Notify the content creator (to be implemented)
        // $this->notifyContentCreator($unit, $review, 'rejected');

        // Log the review rejection
        AuditLog::log(
            'review_rejected',
            'units',
            $unit,
            [],
            [
                'review_id' => $review->id,
                'rejection_reason' => $request->rejection_reason
            ]
        );

        return $this->sendResponse($unit, 'Unit review rejected successfully.');
    }
}
