<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\QuizQuestion;
use App\Models\Quiz;
use App\Http\Requests\API\QuizQuestion\StoreQuizQuestionRequest;
use App\Http\Requests\API\QuizQuestion\UpdateQuizQuestionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminQuizQuestionController extends BaseAPIController
{
    /**
     * Display a listing of all quiz questions.
     */
    public function index(Request $request): JsonResponse
    {
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
    }

    /**
     * Store a newly created quiz question.
     */
    public function store(StoreQuizQuestionRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // If quiz_id is provided, get the quiz
        if (isset($data['quiz_id'])) {
            $quiz = Quiz::findOrFail($data['quiz_id']);
            
            // Get the highest order in the quiz
            $maxOrder = $quiz->questions()->max('order') ?? 0;
            $data['order'] = $maxOrder + 1;
        }
        
        $quizQuestion = QuizQuestion::create($data);

        // Log the creation for audit trail
        activity()
            ->performedOn($quizQuestion)
            ->causedBy($request->user())
            ->withProperties(['data' => $data])
            ->log('created');

        return $this->sendCreatedResponse($quizQuestion, 'Quiz question created successfully.');
    }

    /**
     * Display the specified quiz question.
     */
    public function show(Request $request, QuizQuestion $quizQuestion): JsonResponse
    {
        // Load relationships if requested
        if ($request->has('with_quiz')) {
            $quizQuestion->load('quiz');
        }

        if ($request->has('with_versions')) {
            // Load version history if requested
            $quizQuestion->load('versions');
        }

        return $this->sendResponse($quizQuestion);
    }

    /**
     * Update the specified quiz question.
     */
    public function update(UpdateQuizQuestionRequest $request, QuizQuestion $quizQuestion): JsonResponse
    {
        $oldData = $quizQuestion->toArray();
        $quizQuestion->update($request->validated());

        // Log the update for audit trail
        activity()
            ->performedOn($quizQuestion)
            ->causedBy($request->user())
            ->withProperties([
                'old' => $oldData,
                'new' => $request->validated()
            ])
            ->log('updated');

        return $this->sendResponse($quizQuestion, 'Quiz question updated successfully.');
    }

    /**
     * Remove the specified quiz question.
     */
    public function destroy(Request $request, QuizQuestion $quizQuestion): JsonResponse
    {
        $data = $quizQuestion->toArray();
        $quizQuestion->delete();

        // Log the deletion for audit trail
        activity()
            ->performedOn($quizQuestion)
            ->causedBy($request->user())
            ->withProperties(['data' => $data])
            ->log('deleted');

        return $this->sendNoContentResponse();
    }

    /**
     * Clone a quiz question.
     */
    public function clone(Request $request, QuizQuestion $quizQuestion): JsonResponse
    {
        // Create a new quiz question with the same data
        $newQuizQuestion = $quizQuestion->replicate();
        $newQuizQuestion->question = $quizQuestion->question . ' (Copy)';
        
        // If it's part of a quiz, add it to the end
        if ($quizQuestion->quiz_id) {
            $quiz = Quiz::findOrFail($quizQuestion->quiz_id);
            $maxOrder = $quiz->questions()->max('order') ?? 0;
            $newQuizQuestion->order = $maxOrder + 1;
        }
        
        $newQuizQuestion->save();

        // Log the cloning for audit trail
        activity()
            ->performedOn($newQuizQuestion)
            ->causedBy($request->user())
            ->withProperties([
                'original_id' => $quizQuestion->id,
                'data' => $newQuizQuestion->toArray()
            ])
            ->log('cloned');

        return $this->sendCreatedResponse($newQuizQuestion, 'Quiz question cloned successfully.');
    }
}
