<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\VocabularyItem;
use App\Models\UserProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VocabularyController extends BaseAPIController
{
    /**
     * Get vocabulary items for a lesson
     */
    public function index(Request $request, int $lessonId): JsonResponse
    {
        $items = VocabularyItem::where('lesson_id', $lessonId)
            ->with(['media'])
            ->when($request->has('difficulty'), function($query) use ($request) {
                $query->where('difficulty_level', $request->difficulty);
            })
            ->orderBy('difficulty_level')
            ->get()
            ->map(function ($item) {
                return $item->getWithExamples();
            });

        return $this->sendResponse($items);
    }

    /**
     * Get vocabulary review items
     */
    public function reviewItems(Request $request): JsonResponse
    {
        $request->validate([
            'count' => 'nullable|integer|min:1|max:50',
            'difficulty' => 'nullable|integer|min:1|max:5'
        ]);

        // Get items due for review based on spaced repetition
        $items = $this->getReviewDueItems(
            auth()->id(),
            $request->input('count', 10),
            $request->difficulty
        );

        return $this->sendResponse($items);
    }

    /**
     * Check vocabulary item translation
     */
    public function checkTranslation(Request $request, VocabularyItem $item): JsonResponse
    {
        $request->validate([
            'translation' => 'required|string'
        ]);

        $isCorrect = $item->checkTranslation($request->translation);
        
        // Update progress using spaced repetition
        $this->updateProgress($item, $isCorrect);

        return $this->sendResponse([
            'correct' => $isCorrect,
            'correct_translation' => $isCorrect ? null : $item->translation,
            'similar_words' => $isCorrect ? $item->getSimilarWords(3) : []
        ]);
    }

    /**
     * Get vocabulary statistics
     */
    public function statistics(): JsonResponse
    {
        $userId = auth()->id();
        $progress = UserProgress::where('user_id', $userId)
            ->where('trackable_type', VocabularyItem::class)
            ->get();

        $stats = [
            'total_words_learned' => $progress->where('status', 'completed')->count(),
            'words_in_progress' => $progress->where('status', 'in_progress')->count(),
            'mastery_levels' => $this->calculateMasteryLevels($progress),
            'daily_progress' => $this->getDailyProgress($userId),
            'recent_activity' => $this->getRecentActivity($userId)
        ];

        return $this->sendResponse($stats);
    }

    /**
     * Get items due for review based on spaced repetition
     */
    private function getReviewDueItems(int $userId, int $count, ?int $difficulty = null): array
    {
        $query = VocabularyItem::whereHas('progress', function($query) use ($userId) {
            $query->where('user_id', $userId)
                ->where(function($q) {
                    $q->where('status', 'in_progress')
                        ->orWhere('status', 'completed');
                })
                ->where(function($q) {
                    $q->whereNull('meta_data->next_review')
                        ->orWhere('meta_data->next_review', '<=', now());
                });
        })
        ->when($difficulty, function($query) use ($difficulty) {
            $query->where('difficulty_level', $difficulty);
        })
        ->with(['progress' => function($query) use ($userId) {
            $query->where('user_id', $userId);
        }])
        ->orderBy(DB::raw('RAND()'))
        ->limit($count);

        // Also include some new words if we don't have enough review items
        $reviewItems = $query->get();
        if ($reviewItems->count() < $count) {
            $newItems = VocabularyItem::whereDoesntHave('progress', function($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->when($difficulty, function($query) use ($difficulty) {
                $query->where('difficulty_level', $difficulty);
            })
            ->orderBy(DB::raw('RAND()'))
            ->limit($count - $reviewItems->count())
            ->get();

            $reviewItems = $reviewItems->concat($newItems);
        }

        return $reviewItems->map->getWithExamples()->toArray();
    }

    /**
     * Update progress using spaced repetition
     */
    private function updateProgress(VocabularyItem $item, bool $isCorrect): void
    {
        $progress = UserProgress::firstOrNew([
            'user_id' => auth()->id(),
            'trackable_type' => VocabularyItem::class,
            'trackable_id' => $item->id
        ]);

        $metadata = $progress->meta_data ?? [];
        $reviewCount = ($metadata['review_count'] ?? 0) + 1;
        $correctStreak = $isCorrect ? 
            ($metadata['correct_streak'] ?? 0) + 1 : 
            0;

        // Calculate next review date using spaced repetition
        $nextReview = $this->calculateNextReview($correctStreak);

        $progress->update([
            'status' => $correctStreak >= 5 ? 'completed' : 'in_progress',
            'meta_data' => array_merge($metadata, [
                'review_count' => $reviewCount,
                'correct_streak' => $correctStreak,
                'last_review' => now(),
                'next_review' => $nextReview
            ])
        ]);
    }

    /**
     * Calculate next review date using spaced repetition
     */
    private function calculateNextReview(int $correctStreak): Carbon
    {
        // Using a modified version of SuperMemo 2 algorithm
        $intervals = [
            0 => 0,     // Same day
            1 => 1,     // Next day
            2 => 3,     // 3 days
            3 => 7,     // 1 week
            4 => 14,    // 2 weeks
            5 => 30,    // 1 month
        ];

        $days = $intervals[min($correctStreak, 5)];
        return now()->addDays($days);
    }

    /**
     * Calculate mastery levels
     */
    private function calculateMasteryLevels($progress): array
    {
        return [
            'mastered' => $progress->where('status', 'completed')->count(),
            'familiar' => $progress->where('status', 'in_progress')
                ->filter(function ($p) {
                    return ($p->meta_data['correct_streak'] ?? 0) >= 3;
                })->count(),
            'learning' => $progress->where('status', 'in_progress')
                ->filter(function ($p) {
                    return ($p->meta_data['correct_streak'] ?? 0) < 3;
                })->count()
        ];
    }

    /**
     * Get daily progress
     */
    private function getDailyProgress(int $userId): array
    {
        return UserProgress::where('user_id', $userId)
            ->where('trackable_type', VocabularyItem::class)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->mapWithKeys(function ($record) {
                return [$record->date => [
                    'total' => $record->total,
                    'completed' => $record->completed
                ]];
            })
            ->toArray();
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(int $userId): array
    {
        return UserProgress::where('user_id', $userId)
            ->where('trackable_type', VocabularyItem::class)
            ->with('trackable')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get()
            ->map(function ($progress) {
                return [
                    'word' => $progress->trackable->word,
                    'translation' => $progress->trackable->translation,
                    'status' => $progress->status,
                    'correct_streak' => $progress->meta_data['correct_streak'] ?? 0,
                    'last_review' => $progress->meta_data['last_review']
                ];
            })
            ->toArray();
    }
}