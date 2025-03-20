<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends BaseAPIController
{
    /**
     * Get quiz details with questions
     */
    public function show(Quiz $quiz): JsonResponse
    {
        $quiz->load(['questions' => function ($query) {
            // Exclude correct answers from response
            $query->select(['id', 'quiz_id', 'question', 'type', 'options', 'order'])
                ->orderBy('order');
        }]);

        $attemptCount = UserProgress::where('trackable_type', Quiz::class)
            ->where('trackable_id', $quiz->id)
            ->where('user_id', auth()->id())
            ->count();

        return $this->sendResponse([
            'quiz' => $quiz,
            'attempt_count' => $attemptCount,
            'best_score' => $quiz->getBestScore(auth()->id())
        ]);
    }

    /**
     * Submit a quiz attempt
     */
    public function submit(Request $request, Quiz $quiz): JsonResponse
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.*' => 'required'
        ]);

        $result = $quiz->submitAttempt(auth()->id(), $request->answers);

        // Get detailed feedback for each question
        $feedback = $quiz->questions->map(function ($question) use ($request) {
            $isCorrect = $question->checkAnswer($request->answers[$question->id] ?? null);
            return [
                'question_id' => $question->id,
                'correct' => $isCorrect,
                'correct_answer' => $isCorrect ? null : $question->getCorrectAnswer(),
                'explanation' => $question->explanation
            ];
        });

        return $this->sendResponse([
            'score' => $result['score'],
            'passed' => $result['passed'],
            'required_score' => $result['required_score'],
            'feedback' => $feedback,
            'next_quiz' => $this->getNextQuizSuggestion($quiz, $result['passed'])
        ]);
    }

    /**
     * Get quiz history for the authenticated user
     */
    public function history(Request $request, Quiz $quiz): JsonResponse
    {
        $history = UserProgress::where('trackable_type', Quiz::class)
            ->where('trackable_id', $quiz->id)
            ->where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($attempt) {
                return [
                    'attempt_date' => $attempt->created_at,
                    'score' => $attempt->meta_data['score'],
                    'passed' => $attempt->meta_data['passed'],
                    'time_spent' => $attempt->meta_data['time_spent'],
                    'answers' => $attempt->meta_data['answers']
                ];
            });

        return $this->sendResponse([
            'attempts' => $history,
            'stats' => [
                'average_score' => $history->avg('score'),
                'best_score' => $history->max('score'),
                'total_attempts' => $history->count(),
                'pass_rate' => $history->filter(fn($a) => $a['passed'])->count() / $history->count() * 100
            ]
        ]);
    }

    /**
     * Get quiz statistics
     */
    public function statistics(Quiz $quiz): JsonResponse
    {
        $totalAttempts = UserProgress::where('trackable_type', Quiz::class)
            ->where('trackable_id', $quiz->id)
            ->count();

        if ($totalAttempts === 0) {
            return $this->sendResponse([
                'message' => 'No attempts recorded for this quiz.'
            ]);
        }

        $stats = [
            'total_attempts' => $totalAttempts,
            'average_score' => $quiz->getAverageScore(),
            'completion_rate' => $quiz->getCompletionRate(),
            'difficulty_rating' => $quiz->getDifficultyRating(),
            'question_stats' => $this->getQuestionStats($quiz),
            'time_distribution' => $this->getTimeDistribution($quiz)
        ];

        return $this->sendResponse($stats);
    }

    /**
     * Get suggested next quiz based on performance
     */
    private function getNextQuizSuggestion(Quiz $quiz, bool $passed): ?array
    {
        $nextQuiz = $passed ?
            Quiz::where('unit_id', $quiz->unit_id)
            ->where('order', '>', $quiz->order)
            ->orderBy('order')
            ->first() :
            Quiz::whereIn('id', function ($query) use ($quiz) {
                $query->select('quiz_id')
                    ->from('quiz_questions')
                    ->whereIn('topic', function ($q) use ($quiz) {
                        $q->select('topic')
                            ->from('quiz_questions')
                            ->where('quiz_id', $quiz->id)
                            ->whereIn('id', function ($sq) {
                                $sq->select('question_id')
                                    ->from('user_progress')
                                    ->where('user_id', auth()->id())
                                    ->where('status', 'failed');
                            });
                    })
                    ->where('difficulty_level', '<=', $quiz->difficulty_level);
            })
            ->first();

        if (!$nextQuiz) {
            return null;
        }

        return [
            'id' => $nextQuiz->id,
            'title' => $nextQuiz->title,
            'description' => $nextQuiz->description
        ];
    }

    /**
     * Get statistics for individual questions
     */
    private function getQuestionStats(Quiz $quiz): array
    {
        return QuizQuestion::where('quiz_id', $quiz->id)
            ->select('id', 'question')
            ->withCount([
                'progress as correct_count' => function ($query) {
                    $query->where('meta_data->correct', true);
                },
                'progress as attempt_count'
            ])
            ->get()
            ->map(function ($question) {
                return [
                    'question_id' => $question->id,
                    'question' => $question->question,
                    'success_rate' => $question->attempt_count > 0 ?
                        ($question->correct_count / $question->attempt_count) * 100 : 0
                ];
            })
            ->toArray();
    }

    /**
     * Get time distribution for quiz completion
     */
    private function getTimeDistribution(Quiz $quiz): array
    {
        return DB::table('user_progress')
            ->where('trackable_type', Quiz::class)
            ->where('trackable_id', $quiz->id)
            ->select(DB::raw('
                CASE 
                    WHEN meta_data->"$.time_spent" <= 300 THEN "0-5 min"
                    WHEN meta_data->"$.time_spent" <= 600 THEN "5-10 min"
                    WHEN meta_data->"$.time_spent" <= 900 THEN "10-15 min"
                    ELSE "15+ min"
                END as time_range
            '))
            ->groupBy('time_range')
            ->orderBy('time_range')
            ->pluck(DB::raw('COUNT(*) as count'), 'time_range')
            ->toArray();
    }
}
