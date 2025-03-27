<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Http\Controllers\API\SectionController as BaseSectionController;
use App\Models\Section;
use App\Models\Lesson;
use App\Http\Requests\API\Section\StoreSectionRequest;
use App\Http\Requests\API\Section\UpdateSectionRequest;
use App\Models\AuditLog;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminSectionController extends BaseAPIController
{
    // Constructor remains the same
    
    public function index(Request $request): JsonResponse
    {
        try {
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
        } catch (Exception $e) {
            Log::error('Failed to fetch sections: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to fetch sections', [], 500);
        }
    }

    public function store(StoreSectionRequest $request): JsonResponse
    {
        try {
            $section = Section::create($request->validated());

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'sections',
                $section,
                [],
                $request->validated()
            );

            return $this->sendCreatedResponse($section, 'Section created successfully.');
        } catch (Exception $e) {
            Log::error('Failed to create section: ' . $e->getMessage(), [
                'data' => $request->validated(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to create section', [], 500);
        }
    }

    public function show(Request $request, Section $section): JsonResponse
    {
        try {
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
                $section->load('versions');
            }

            if ($request->has('with_reviews')) {
                $section->load('reviews');
            }

            return $this->sendResponse($section);
        } catch (Exception $e) {
            Log::error('Failed to fetch section details: ' . $e->getMessage(), [
                'section_id' => $section->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to fetch section details', [], 500);
        }
    }

    public function update(UpdateSectionRequest $request, Section $section): JsonResponse
    {
        try {
            $oldData = $section->toArray();
            $section->update($request->validated());

            // Log the update for audit trail
            AuditLog::logChange(
                $section,
                'update',
                $oldData,
                $section->toArray()
            );

            return $this->sendResponse($section, 'Section updated successfully.');
        } catch (Exception $e) {
            Log::error('Failed to update section: ' . $e->getMessage(), [
                'section_id' => $section->id,
                'data' => $request->validated(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to update section', [], 500);
        }
    }

    public function destroy(Request $request, Section $section): JsonResponse
    {
        try {
            // Prevent deletion of published sections
            if ($section->status === 'published') {
                return $this->sendError('Cannot delete a published section. Archive it first.', ['status' => 422]);
            }

            $data = $section->toArray();
            $section->delete();

            // Log the deletion for audit trail
            AuditLog::log(
                'delete',
                'sections',
                $section,
                $data,
                []
            );

            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            Log::error('Failed to delete section: ' . $e->getMessage(), [
                'section_id' => $section->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to delete section', [], 500);
        }
    }

    public function updateStatus(Request $request, Section $section): JsonResponse
    {
        try {
            $request->validate([
                'status' => ['required', 'string', 'in:draft,published,archived']
            ]);

            $oldStatus = $section->status;
            $section->status = $request->status;
            $section->save();

            // Log the status change for audit trail
            AuditLog::log(
                'status_update',
                'sections',
                $section,
                ['status' => $oldStatus],
                ['status' => $request->status]
            );

            return $this->sendResponse($section, 'Section status updated successfully.');
        } catch (Exception $e) {
            Log::error('Failed to update section status: ' . $e->getMessage(), [
                'section_id' => $section->id,
                'status' => $request->status,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to update section status', [], 500);
        }
    }

    public function reorderExercises(Request $request, Section $section): JsonResponse
    {
        try {
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
        } catch (Exception $e) {
            Log::error('Failed to reorder exercises: ' . $e->getMessage(), [
                'section_id' => $section->id,
                'exercise_ids' => $request->exercise_ids ?? [],
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to reorder exercises', [], 500);
        }
    }

    public function submitForReview(Request $request, Section $section): JsonResponse
    {
        try {
            // Validate that the section is in draft status
            if ($section->status !== 'draft') {
                return $this->sendError('Only draft sections can be submitted for review.', ['status' => 422]);
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

            // Log the review submission
            AuditLog::log(
                'submit_for_review',
                'sections',
                $section,
                [],
                ['review_id' => $review->id]
            );

            return $this->sendResponse($section, 'Section submitted for review successfully.');
        } catch (Exception $e) {
            Log::error('Failed to submit section for review: ' . $e->getMessage(), [
                'section_id' => $section->id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to submit section for review', [], 500);
        }
    }

    public function approveReview(Request $request, Section $section): JsonResponse
    {
        try {
            // Validate that the section is in review status
            if ($section->review_status !== 'pending') {
                return $this->sendError('This section is not pending review.', ['status' => 422]);
            }

            $request->validate([
                'review_comment' => ['nullable', 'string', 'max:1000'],
            ]);

            // Get the latest pending review
            $review = $section->reviews()->where('status', 'pending')->latest()->first();
            
            if (!$review) {
                return $this->sendError('No pending review found for this section.', ['status' => 404]);
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

            // Log the review approval
            AuditLog::log(
                'approve_review',
                'sections',
                $section,
                [],
                ['review_id' => $review->id]
            );

            return $this->sendResponse($section, 'Section review approved successfully.');
        } catch (Exception $e) {
            Log::error('Failed to approve section review: ' . $e->getMessage(), [
                'section_id' => $section->id,
                'user_id' => $request->user()->id ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to approve section review', [], 500);
        }
    }

    public function rejectReview(Request $request, Section $section): JsonResponse
    {
        try {
            // Validate that the section is in review status
            if ($section->review_status !== 'pending') {
                return $this->sendError('This section is not pending review.', ['status' => 422]);
            }

            $request->validate([
                'review_comment' => ['required', 'string', 'max:1000'],
                'rejection_reason' => ['required', 'string', 'in:content_issues,formatting_issues,accuracy_issues,other'],
            ]);

            // Get the latest pending review
            $review = $section->reviews()->where('status', 'pending')->latest()->first();
            
            if (!$review) {
                return $this->sendError('No pending review found for this section.', ['status' => 404]);
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

            // Log the review rejection
            AuditLog::log(
                'reject_review',
                'sections',
                $section,
                [],
                [
                    'review_id' => $review->id,
                    'rejection_reason' => $request->rejection_reason
                ]
            );

            return $this->sendResponse($section, 'Section review rejected successfully.');
        } catch (Exception $e) {
            Log::error('Failed to reject section review: ' . $e->getMessage(), [
                'section_id' => $section->id,
                'user_id' => $request->user()->id ?? null,
                'rejection_reason' => $request->rejection_reason ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to reject section review', [], 500);
        }
    }
}
