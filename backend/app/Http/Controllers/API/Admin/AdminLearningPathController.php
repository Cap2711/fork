<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\LearningPath;
use App\Http\Requests\API\LearningPath\StoreLearningPathRequest;
use App\Http\Requests\API\LearningPath\UpdateLearningPathRequest;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLearningPathController extends BaseAPIController
{
    /**
     * Display a listing of all learning paths for admin.
     * Admins can see all learning paths including drafts and archived.
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
        AuditLog::log(
            'create',
            'learning_paths',
            $learningPath,
            [],
            $request->validated()
        );

        return $this->sendCreatedResponse($learningPath, 'Learning path created successfully.');
    }

    /**
     * Display the specified learning path for admin.
     * Admins can see additional information like version history.
     */
    public function show(Request $request, LearningPath $learningPath): JsonResponse
    {
        // Load relationships if requested
        if ($request->has('with_units')) {
            $learningPath->load(['units' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_versions')) {
            // Load version history if requested
            $learningPath->load('versions');
        }

        if ($request->has('with_reviews')) {
            // Load reviews if requested
            $learningPath->load('reviews');
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
        AuditLog::logChange(
            $learningPath,
            'update',
            $oldData,
            $learningPath->toArray()
        );

        return $this->sendResponse($learningPath, 'Learning path updated successfully.');
    }

    /**
     * Remove the specified learning path.
     * Admins can delete learning paths that aren't published.
     */
    public function destroy(Request $request, LearningPath $learningPath): JsonResponse
    {
        // Prevent deletion of published learning paths
        if ($learningPath->status === 'published') {
            return $this->sendError('Cannot delete a published learning path. Archive it first.', ['status' => 422]);
        }

        $data = $learningPath->toArray();
        
        // Detach from all units
        $learningPath->units()->detach();
        
        $learningPath->delete();

        // Log the deletion for audit trail
        AuditLog::log(
            'delete',
            'learning_paths',
            $learningPath,
            $data,
            []
        );

        return $this->sendNoContentResponse();
    }

    /**
     * Update the status of a learning path.
     * Admin-specific method to change learning path status.
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
        AuditLog::log(
            'status_update',
            'learning_paths',
            $learningPath,
            ['status' => $oldStatus],
            ['status' => $request->status]
        );

        return $this->sendResponse($learningPath, 'Learning path status updated successfully.');
    }

    /**
     * Reorder units within a learning path.
     * Admin-specific method to reorder units.
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

    /**
     * Submit a learning path for review.
     */
    public function submitForReview(Request $request, LearningPath $learningPath): JsonResponse
    {
        // Validate that the learning path is in draft status
        if ($learningPath->status !== 'draft') {
            return $this->sendError('Only draft learning paths can be submitted for review.', ['status' => 422]);
        }

        // Update the learning path status to 'in_review'
        $learningPath->review_status = 'pending';
        $learningPath->save();

        // Create a review record
        $review = $learningPath->reviews()->create([
            'submitted_by' => $request->user()->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Notify reviewers (to be implemented)
        // $this->notifyReviewers($learningPath, $review);

        // Log the review submission
        AuditLog::log(
            'submit_for_review',
            'learning_paths',
            $learningPath,
            [],
            [],
            [
                'metadata' => ['review_id' => $review->id]
            ]
        );

        return $this->sendResponse($learningPath, 'Learning path submitted for review successfully.');
    }

    /**
     * Approve a learning path review.
     */
    public function approveReview(Request $request, LearningPath $learningPath): JsonResponse
    {
        // Validate that the learning path is in review status
        if ($learningPath->review_status !== 'pending') {
            return $this->sendError('This learning path is not pending review.', ['status' => 422]);
        }

        $request->validate([
            'review_comment' => ['nullable', 'string', 'max:1000'],
        ]);

        // Get the latest pending review
        $review = $learningPath->reviews()->where('status', 'pending')->latest()->first();
        
        if (!$review) {
            return $this->sendError('No pending review found for this learning path.', ['status' => 404]);
        }

        // Update the review
        $review->update([
            'reviewed_by' => $request->user()->id,
            'status' => 'approved',
            'review_comment' => $request->review_comment,
            'reviewed_at' => now(),
        ]);

        // Update the learning path status
        $learningPath->review_status = 'approved';
        $learningPath->save();

        // Notify the content creator (to be implemented)
        // $this->notifyContentCreator($learningPath, $review, 'approved');

        // Log the review approval
        AuditLog::log(
            'review_approved',
            'learning_paths',
            $learningPath,
            [],
            [],
            [
                'metadata' => ['review_id' => $review->id]
            ]
        );

        return $this->sendResponse($learningPath, 'Learning path review approved successfully.');
    }

    /**
     * Reject a learning path review.
     */
    public function rejectReview(Request $request, LearningPath $learningPath): JsonResponse
    {
        // Validate that the learning path is in review status
        if ($learningPath->review_status !== 'pending') {
            return $this->sendError('This learning path is not pending review.', ['status' => 422]);
        }

        $request->validate([
            'review_comment' => ['required', 'string', 'max:1000'],
            'rejection_reason' => ['required', 'string', 'in:content_issues,formatting_issues,accuracy_issues,other'],
        ]);

        // Get the latest pending review
        $review = $learningPath->reviews()->where('status', 'pending')->latest()->first();
        
        if (!$review) {
            return $this->sendError('No pending review found for this learning path.', ['status' => 404]);
        }

        // Update the review
        $review->update([
            'reviewed_by' => $request->user()->id,
            'status' => 'rejected',
            'review_comment' => $request->review_comment,
            'rejection_reason' => $request->rejection_reason,
            'reviewed_at' => now(),
        ]);

        // Update the learning path status
        $learningPath->review_status = 'rejected';
        $learningPath->save();

        // Notify the content creator (to be implemented)
        // $this->notifyContentCreator($learningPath, $review, 'rejected');

        // Log the review rejection
        AuditLog::log(
            'review_rejected',
            'learning_paths',
            $learningPath,
            [],
            [],
            [
                'metadata' => [
                    'review_id' => $review->id,
                    'rejection_reason' => $request->rejection_reason
                ]
            ]
        );

        return $this->sendResponse($learningPath, 'Learning path review rejected successfully.');
    }
}
