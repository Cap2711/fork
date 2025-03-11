<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Quiz;
use App\Models\Section;
use App\Http\Requests\API\Quiz\StoreQuizRequest;
use App\Http\Requests\API\Quiz\UpdateQuizRequest;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminQuizController extends BaseAPIController
{
    /**
     * Display a listing of all quizzes.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Quiz::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('section_id')) {
            $query->whereHas('sections', function ($query) use ($request) {
                $query->where('sections.id', $request->section_id);
            });
        }

        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        // Include relationships if requested
        if ($request->has('with_sections')) {
            $query->with('sections');
        }

        if ($request->has('with_questions')) {
            $query->with(['questions' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        $perPage = $request->input('per_page', 15);
        $quizzes = $query->paginate($perPage);

        return $this->sendPaginatedResponse($quizzes);
    }

    /**
     * Store a newly created quiz.
     */
    public function store(StoreQuizRequest $request): JsonResponse
    {
        $quiz = Quiz::create($request->validated());

        // Attach to section if specified
        if ($request->has('section_id')) {
            $section = Section::findOrFail($request->section_id);

            // Get the highest order in the section
            $maxOrder = $section->quizzes()->max('order') ?? 0;

            // Attach with the next order
            $section->quizzes()->attach($quiz->id, ['order' => $maxOrder + 1]);
        }

        // Log the creation for audit trail
        AuditLog::log(
            'create',
            'quizzes',
            $quiz,
            [],
            $request->validated()
        );

        return $this->sendCreatedResponse($quiz, 'Quiz created successfully.');
    }

    /**
     * Display the specified quiz.
     */
    public function show(Request $request, Quiz $quiz): JsonResponse
    {
        // Load relationships if requested
        if ($request->has('with_sections')) {
            $quiz->load('sections');
        }

        if ($request->has('with_questions')) {
            $quiz->load(['questions' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        if ($request->has('with_versions')) {
            // Load version history if requested
            $quiz->load('versions');
        }

        return $this->sendResponse($quiz);
    }

    /**
     * Update the specified quiz.
     */
    public function update(UpdateQuizRequest $request, Quiz $quiz): JsonResponse
    {
        $oldData = $quiz->toArray();
        $quiz->update($request->validated());

        // Log the update for audit trail
        AuditLog::logChange(
            $quiz,
            'update',
            $oldData,
            $quiz->toArray()
        );

        return $this->sendResponse($quiz, 'Quiz updated successfully.');
    }

    /**
     * Remove the specified quiz.
     */
    public function destroy(Request $request, Quiz $quiz): JsonResponse
    {
        // Prevent deletion of published quizzes
        if ($quiz->status === 'published') {
            return $this->sendError('Cannot delete a published quiz. Archive it first.', ['status' => 422]);
        }

        $data = $quiz->toArray();

        // Detach from all sections
        $quiz->sections()->detach();

        $quiz->delete();

        // Log the deletion for audit trail
        AuditLog::log(
            'delete',
            'quizzes',
            $quiz,
            $data,
            []
        );

        return $this->sendNoContentResponse();
    }

    /**
     * Update the status of a quiz.
     */
    public function updateStatus(Request $request, Quiz $quiz): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:draft,published,archived']
        ]);

        $oldStatus = $quiz->status;
        $quiz->status = $request->status;
        $quiz->save();

        // Log the status change for audit trail
        AuditLog::log(
            'status_update',
            'quizzes',
            $quiz,
            ['status' => $oldStatus],
            ['status' => $request->status]
        );

        return $this->sendResponse($quiz, 'Quiz status updated successfully.');
    }

    /**
     * Reorder questions within a quiz.
     */
    public function reorderQuestions(Request $request, Quiz $quiz): JsonResponse
    {
        $request->validate([
            'question_ids' => ['required', 'array'],
            'question_ids.*' => ['exists:quiz_questions,id']
        ]);

        $questionIds = $request->question_ids;

        // Update the order of each question
        foreach ($questionIds as $index => $questionId) {
            $quiz->questions()->updateExistingPivot($questionId, ['order' => $index + 1]);
        }

        return $this->sendResponse($quiz->load('questions'), 'Questions reordered successfully.');
    }

    /**
     * Clone a quiz.
     */
    public function clone(Request $request, Quiz $quiz): JsonResponse
    {
        // Create a new quiz with the same data
        $newQuiz = $quiz->replicate();
        $newQuiz->title = $quiz->title . ' (Copy)';
        $newQuiz->status = 'draft';
        $newQuiz->save();

        // Clone the questions as well
        foreach ($quiz->questions as $question) {
            $newQuestion = $question->replicate();
            $newQuestion->quiz_id = $newQuiz->id;
            $newQuestion->save();
        }

        // Log the cloning for audit trail
        AuditLog::log(
            'clone',
            'quizzes',
            $newQuiz,
            ['original_id' => $quiz->id],
            $newQuiz->toArray()
        );

        return $this->sendCreatedResponse($newQuiz, 'Quiz cloned successfully.');
    }

    
}