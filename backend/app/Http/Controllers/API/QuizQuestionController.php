<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;

class QuizQuestionController extends BaseAPIController
{
/**
* Display the specified quiz question.
* Regular users can only view published questions.
*/
public function show(QuizQuestion $quizQuestion)
{
if ($quizQuestion->status !== 'published') {
return $this->sendError('Question not found', [], 404);
}

    return $this->sendResponse($quizQuestion, 'Quiz question retrieved successfully');
}

/**
 * Submit an answer for the quiz question.
 */
public function submitAnswer(Request $request, QuizQuestion $quizQuestion)
{
    $request->validate([
        'answer' => 'required'
    ]);

    $isCorrect = $quizQuestion->checkAnswer($request->answer);

    // Record user's progress
    auth()->user()->progress()->create([
        'quiz_question_id' => $quizQuestion->id,
        'is_correct' => $isCorrect,
        'answer_given' => $request->answer
    ]);

    return $this->sendResponse([
        'correct' => $isCorrect,
        'explanation' => $isCorrect ? $quizQuestion->success_message : $quizQuestion->failure_message
    ], 'Answer submitted successfully');
}

/**
 * List questions for a specific quiz.
 * Regular users can only see published questions.
 */
public function index(Request $request)
{
    $questions = QuizQuestion::where('quiz_id', $request->quiz_id)
        ->where('status', 'published')
        ->orderBy('order')
        ->get();

    return $this->sendResponse($questions, 'Quiz questions retrieved successfully');
}
}
