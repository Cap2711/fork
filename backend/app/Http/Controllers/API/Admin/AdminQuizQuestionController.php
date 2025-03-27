<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\QuizQuestion;
use App\Models\Quiz;
use App\Http\Requests\API\QuizQuestion\StoreQuizQuestionRequest;
use App\Http\Requests\API\QuizQuestion\UpdateQuizQuestionRequest;
use App\Models\AuditLog;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminQuizQuestionController extends BaseAPIController
{
    /**
     * Display a listing of all quiz questions.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = QuizQuestion::query();

            // Apply filters
            if ($request->has('quiz_id')) {
                $query->where('quiz_id', $request->quiz_id);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('difficulty')) {
                $query->where('difficulty', $request->difficulty);
            }

            // Include relationships if requested
            if ($request->has('with_quiz')) {
                $query->with('quiz');
            }

            $perPage = $request->input('per_page', 15);
            $quizQuestions = $query->paginate($perPage);

            return $this->sendPaginatedResponse($quizQuestions);
        } catch (Exception $e) {
            Log::error('Failed to fetch quiz questions: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to fetch quiz questions', [], 500);
        }
    }

    /**
     * Store a newly created quiz question.
     */
    public function store(StoreQuizQuestionRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $data = $request->validated();
            
            // If quiz_id is provided, get the quiz
            if (isset($data['quiz_id'])) {
                try {
                    $quiz = Quiz::findOrFail($data['quiz_id']);
                    
                    // Get the highest order in the quiz
                    $maxOrder = $quiz->questions()->max('order') ?? 0;
                    $data['order'] = $maxOrder + 1;
                } catch (ModelNotFoundException $e) {
                    DB::rollBack();
                    return $this->sendError('Quiz not found', ['quiz_id' => 'Invalid quiz ID'], 404);
                }
            }
            
            $quizQuestion = QuizQuestion::create($data);

            // Log the creation for audit trail
            AuditLog::log(
                'create',
                'quiz_questions',
                $quizQuestion,
                [],
                $quizQuestion->toArray()
            );

            DB::commit();
            return $this->sendCreatedResponse($quizQuestion, 'Quiz question created successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to create quiz question: ' . $e->getMessage(), [
                'data' => $request->validated(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to create quiz question: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Display the specified quiz question.
     */
    public function show(Request $request, QuizQuestion $quizQuestion): JsonResponse
    {
        try {
            // Load relationships if requested
            if ($request->has('with_quiz')) {
                $quizQuestion->load('quiz');
            }

            if ($request->has('with_versions')) {
                // Load version history if requested
                $quizQuestion->load('versions');
            }

            return $this->sendResponse($quizQuestion);
        } catch (Exception $e) {
            Log::error('Failed to fetch quiz question: ' . $e->getMessage(), [
                'question_id' => $quizQuestion->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to fetch quiz question', [], 500);
        }
    }

    /**
     * Update the specified quiz question.
     */
    public function update(UpdateQuizQuestionRequest $request, QuizQuestion $quizQuestion): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $oldData = $quizQuestion->toArray();
            $quizQuestion->update($request->validated());

            // Log the update for audit trail
            AuditLog::log(
                'update',
                'quiz_questions',
                $quizQuestion,
                $oldData,
                $request->validated()
            );

            DB::commit();
            return $this->sendResponse($quizQuestion, 'Quiz question updated successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to update quiz question: ' . $e->getMessage(), [
                'question_id' => $quizQuestion->id,
                'data' => $request->validated(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to update quiz question: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Remove the specified quiz question.
     */
    public function destroy(Request $request, QuizQuestion $quizQuestion): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $data = $quizQuestion->toArray();
            $quizQuestion->delete();

            // Log the deletion for audit trail
            AuditLog::log(
                'delete',
                'quiz_questions',
                $quizQuestion,
                $data
            );

            DB::commit();
            return $this->sendNoContentResponse();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete quiz question: ' . $e->getMessage(), [
                'question_id' => $quizQuestion->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to delete quiz question: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Clone a quiz question.
     */
    public function clone(Request $request, QuizQuestion $quizQuestion): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            // Create a new quiz question with the same data
            $newQuizQuestion = $quizQuestion->replicate();
            $newQuizQuestion->question = $quizQuestion->question . ' (Copy)';
            
            // If it's part of a quiz, add it to the end
            if ($quizQuestion->quiz_id) {
                try {
                    $quiz = Quiz::findOrFail($quizQuestion->quiz_id);
                    $maxOrder = $quiz->questions()->max('order') ?? 0;
                    $newQuizQuestion->order = $maxOrder + 1;
                } catch (ModelNotFoundException $e) {
                    DB::rollBack();
                    return $this->sendError('Quiz not found', ['quiz_id' => 'Invalid quiz ID'], 404);
                }
            }
            
            $newQuizQuestion->save();

            // Log the cloning for audit trail
            AuditLog::log(
                'clone',
                'quiz_questions',
                $newQuizQuestion,
                [],
                [
                    'original_id' => $quizQuestion->id,
                    'data' => $newQuizQuestion->toArray()
                ]
            );

            DB::commit();
            return $this->sendCreatedResponse($newQuizQuestion, 'Quiz question cloned successfully.');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to clone quiz question: ' . $e->getMessage(), [
                'original_id' => $quizQuestion->id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->sendError('Failed to clone quiz question: ' . $e->getMessage(), [], 500);
        }
    }
}
