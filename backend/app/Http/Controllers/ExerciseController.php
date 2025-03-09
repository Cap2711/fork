<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\User;
use App\Models\UserExerciseProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExerciseController extends Controller
{
    /**
     * Get exercise details with user progress
     */
    public function show(Exercise $exercise)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $progress = $this->getProgressForUser($user, $exercise);
        
        return response()->json([
            'exercise' => $exercise->load('hints'),
            'progress' => $progress
        ]);
    }

    /**
     * Submit an exercise attempt
     */
    public function submit(Request $request, Exercise $exercise)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'answer' => 'required',
            'hint_used' => 'boolean',
            'time_taken' => 'required|integer|min:0'
        ]);

        $progress = $this->getProgressForUser($user, $exercise);
        $isCorrect = $this->checkAnswer($exercise, $validated['answer']);
        
        // Update progress
        $progress->attempts += 1;
        $progress->hint_used_at = $validated['hint_used'] ? now() : $progress->hint_used_at;
        $progress->total_time += $validated['time_taken'];
        
        if ($isCorrect && !$progress->completed) {
            $progress->completed = true;
            $progress->completed_at = now();
            $progress->score = $this->calculateScore(
                $progress->attempts,
                $validated['time_taken'],
                $validated['hint_used']
            );
        }
        
        $progress->save();

        return response()->json([
            'correct' => $isCorrect,
            'progress' => $progress,
            'score' => $progress->score ?? 0
        ]);
    }

    /**
     * Get or create progress record for user
     */
    private function getProgressForUser(User $user, Exercise $exercise): UserExerciseProgress
    {
        return UserExerciseProgress::firstOrCreate(
            [
                'user_id' => $user->id,
                'exercise_id' => $exercise->id
            ],
            [
                'attempts' => 0,
                'completed' => false,
                'score' => 0,
                'total_time' => 0
            ]
        );
    }

    /**
     * Check if the submitted answer is correct
     */
    private function checkAnswer(Exercise $exercise, mixed $answer): bool
    {
        // Compare with exercise's correct_answer based on exercise type
        $correctAnswer = json_decode($exercise->correct_answer, true);
        $submittedAnswer = is_string($answer) ? json_decode($answer, true) : $answer;

        // TODO: Implement type-specific answer checking
        return $correctAnswer === $submittedAnswer;
    }

    /**
     * Calculate score based on attempts, time, and hint usage
     */
    private function calculateScore(int $attempts, int $timeTaken, bool $hintUsed): int
    {
        $baseScore = 100;
        
        // Deduct points for multiple attempts
        $attemptPenalty = ($attempts - 1) * 10;
        
        // Deduct points for using hints
        $hintPenalty = $hintUsed ? 20 : 0;
        
        // Time-based penalty (1 point per 10 seconds after first minute)
        $timePenalty = max(0, floor(($timeTaken - 60) / 10));
        
        $finalScore = $baseScore - $attemptPenalty - $hintPenalty - $timePenalty;
        
        return max(0, $finalScore); // Ensure score doesn't go below 0
    }
}