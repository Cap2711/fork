<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciseController extends BaseAPIController
{
    /**
     * Get exercises for a specific section
     */
    public function index(Request $request, int $sectionId): JsonResponse
    {
        $exercises = Exercise::where('section_id', $sectionId)
            ->orderBy('order')
            ->with(['media'])
            ->get()
            ->map(function ($exercise) {
                // Remove correct answers for client-side display
                $content = $exercise->content;
                unset($content['answers']);
                return [
                    'id' => $exercise->id,
                    'type' => $exercise->type,
                    'content' => $content,
                    'order' => $exercise->order,
                    'media' => $exercise->media
                ];
            });

        return $this->sendResponse($exercises);
    }

    /**
     * Check exercise answer
     */
    public function checkAnswer(Request $request, Exercise $exercise): JsonResponse
    {
        $request->validate([
            'answer' => 'required'
        ]);

        $isCorrect = $exercise->checkAnswer($request->answer);
        
        // Record progress
        UserProgress::updateOrCreate(
            [
                'user_id' => auth()->id(),
                'trackable_type' => Exercise::class,
                'trackable_id' => $exercise->id
            ],
            [
                'status' => $isCorrect ? 'completed' : 'failed',
                'meta_data' => [
                    'attempts' => DB::raw('COALESCE(meta_data->>"$.attempts", 0) + 1'),
                    'last_attempt' => now(),
                    'correct' => $isCorrect
                ]
            ]
        );

        return $this->sendResponse([
            'correct' => $isCorrect,
            'feedback' => $this->getFeedback($exercise, $isCorrect),
            'correct_answer' => $isCorrect ? null : $exercise->getHint()
        ]);
    }

    /**
     * Get contextual feedback for the exercise attempt
     */
    private function getFeedback(Exercise $exercise, bool $isCorrect): string
    {
        if ($isCorrect) {
            return array_random([
                "¡Excelente! (Excellent!)",
                "¡Muy bien! (Very good!)",
                "¡Perfecto! (Perfect!)"
            ]);
        }

        return $exercise->type === Exercise::TYPE_WRITING ?
            "Check your spelling and try again!" :
            "Not quite right. Try again!";
    }
}