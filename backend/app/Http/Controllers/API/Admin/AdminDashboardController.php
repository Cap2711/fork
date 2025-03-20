<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Language;
use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Lesson;
use App\Models\Exercise;
use App\Models\UserProgress;
use App\Models\UserStreak;
use App\Models\XpHistory;
use App\Models\Achievement;
use App\Models\UserFeedback;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function engagement(): JsonResponse
    {
        $today = Carbon::today();
        
        // Get daily active users - users who completed at least one lesson today
        $dailyActiveUsers = UserProgress::whereDate('created_at', $today)
            ->distinct('user_id')
            ->count();
        
        // Get streak statistics
        $streakStats = UserStreak::selectRaw('
            COUNT(*) as users_with_streaks,
            AVG(current_streak) as average_streak,
            MAX(current_streak) as longest_current_streak')
            ->first();

        // Calculate user retention (active in last 7 days)
        $newUsersLastWeek = User::where('created_at', '>=', Carbon::now()->subDays(7))->count();
        $retainedUsers = User::whereHas('progress', function($query) {
            $query->where('created_at', '>=', Carbon::now()->subDays(7));
        })->count();
        
        $retentionRate = $newUsersLastWeek > 0 
            ? ($retainedUsers / $newUsersLastWeek) * 100
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'daily_active_users' => $dailyActiveUsers,
                'streak_statistics' => [
                    'users_with_streaks' => $streakStats->users_with_streaks,
                    'average_streak_length' => round($streakStats->average_streak, 1),
                    'longest_current_streak' => $streakStats->longest_current_streak
                ],
                'engagement_metrics' => [
                    'new_users_today' => User::whereDate('created_at', $today)->count(),
                    'lessons_completed_today' => UserProgress::where('status', 'completed')
                        ->whereDate('created_at', $today)
                        ->count(),
                    'total_xp_earned_today' => XpHistory::whereDate('created_at', $today)
                        ->sum('amount')
                ],
                'retention_rate' => round($retentionRate, 2)
            ]
        ]);
    }

    public function progress(): JsonResponse
    {
        // Calculate completion rates
        $overallStats = UserProgress::where('status', 'completed')
            ->selectRaw('
                COUNT(*) as total_completions,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT trackable_id) as unique_lessons')
            ->first();

        // Get completion rates by language
        $languageStats = Language::withCount([
            'learningPaths',
            'learningPaths as completed_lessons' => function($query) {
                $query->whereHas('units.lessons.progress', function($q) {
                    $q->where('status', 'completed');
                });
            }
        ])->get()->mapWithKeys(function($language) {
            $total = $language->learning_paths_count;
            $completed = $language->completed_lessons;
            
            return [
                $language->name => [
                    'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
                    'total_paths' => $total,
                    'completed_lessons' => $completed
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'completion_rates' => [
                    'overall' => $overallStats->total_completions > 0 
                        ? round(($overallStats->unique_users / User::count()) * 100, 2)
                        : 0,
                    'by_language' => $languageStats
                ],
                'popular_content' => [
                    'lessons' => Lesson::withCount('progress')
                        ->orderByDesc('progress_count')
                        ->take(5)
                        ->get(['id', 'title', 'progress_count']),
                    'paths' => LearningPath::withCount('progress')
                        ->orderByDesc('progress_count')
                        ->take(5)
                        ->get(['id', 'title', 'progress_count'])
                ],
                'learning_metrics' => [
                    'average_time_per_lesson' => UserProgress::where('status', 'completed')
                        ->whereNotNull('started_at')
                        ->whereNotNull('completed_at')
                        ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, started_at, completed_at)')),
                    'total_active_learners' => UserProgress::distinct('user_id')->count(),
                    'lessons_completed_this_week' => UserProgress::where('status', 'completed')
                        ->where('created_at', '>=', Carbon::now()->startOfWeek())
                        ->count()
                ]
            ]
        ]);
    }

    public function achievements(): JsonResponse
    {
        // Get all achievements with user counts
        $achievements = Achievement::withCount('users')
            ->get()
            ->map(function($achievement) {
                $totalUsers = User::count();
                return [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'total_earners' => $achievement->users_count,
                    'completion_rate' => $totalUsers > 0 
                        ? round(($achievement->users_count / $totalUsers) * 100, 2)
                        : 0
                ];
            });

        // Get top achievers
        $topAchievers = User::withCount('achievements')
            ->orderByDesc('achievements_count')
            ->limit(10)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'achievements_count' => $user->achievements_count,
                    'latest_achievement' => $user->achievements()
                        ->latest('user_achievement.created_at')
                        ->first(['name', 'description'])
                ];
            });

        // Calculate achievement statistics
        $totalAchievements = $achievements->count();
        $averageCompletion = $achievements->avg('completion_rate');

        return response()->json([
            'success' => true,
            'data' => [
                'total_achievements' => $totalAchievements,
                'average_completion_rate' => round($averageCompletion, 2),
                'achievements' => $achievements,
                'top_achievers' => $topAchievers
            ]
        ]);
    }

    public function leaderboards(): JsonResponse
    {
        $now = Carbon::now();

        return response()->json([
            'success' => true,
            'data' => [
                'daily_leaders' => $this->getLeaderboard($now->copy()->startOfDay()),
                'weekly_leaders' => $this->getLeaderboard($now->copy()->startOfWeek()),
                'monthly_leaders' => $this->getLeaderboard($now->copy()->startOfMonth()),
                'all_time_stats' => [
                    'total_xp_awarded' => XpHistory::sum('amount'),
                    'average_user_xp' => round(XpHistory::avg('amount'), 2),
                    'highest_single_day' => XpHistory::selectRaw("DATE(created_at) as date, SUM(amount) as total")
                        ->groupBy('date')
                        ->orderByDesc('total')
                        ->first()
                ]
            ]
        ]);
    }

    protected function getLeaderboard(Carbon $startDate): array
    {
        return XpHistory::where('created_at', '>=', $startDate)
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(amount) as total_xp, COUNT(*) as activities')
            ->with('user:id,name')
            ->orderByDesc('total_xp')
            ->take(10)
            ->get()
            ->map(function($entry) {
                return [
                    'user' => [
                        'id' => $entry->user->id,
                        'name' => $entry->user->name
                    ],
                    'xp' => $entry->total_xp,
                    'activities' => $entry->activities
                ];
            })
            ->toArray();
    }

    public function contentHealth(): JsonResponse
    {
        // Find content that might need review
        $needsReview = [
            'lessons' => Lesson::withCount(['progress', 'progress as completed_count' => function($query) {
                $query->where('status', 'completed');
            }])
            ->having('progress_count', '>', 10)
            ->havingRaw('(completed_count / progress_count) < 0.5')
            ->with('unit.learningPath')
            ->get()
            ->map(function($lesson) {
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'path' => $lesson->unit->learningPath->title,
                    'completion_rate' => round(($lesson->completed_count / $lesson->progress_count) * 100, 2)
                ];
            }),
            
            'exercises' => Exercise::withCount(['attempts', 'attempts as correct_count' => function($query) {
                $query->where('is_correct', true);
            }])
            ->having('attempts_count', '>', 10)
            ->havingRaw('(correct_count / attempts_count) < 0.5')
            ->with('lesson')
            ->get()
            ->map(function($exercise) {
                return [
                    'id' => $exercise->id,
                    'type' => $exercise->type,
                    'lesson' => $exercise->lesson->title,
                    'success_rate' => round(($exercise->correct_count / $exercise->attempts_count) * 100, 2)
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'needs_review' => $needsReview,
                'user_feedback' => UserFeedback::with(['user:id,name', 'content'])
                    ->latest()
                    ->take(10)
                    ->get()
                    ->map(function($feedback) {
                        return [
                            'id' => $feedback->id,
                            'user' => $feedback->user->name,
                            'content_type' => $feedback->content_type,
                            'content_title' => $feedback->content->title ?? 'Unknown',
                            'feedback' => $feedback->message,
                            'rating' => $feedback->rating,
                            'created_at' => $feedback->created_at->toISOString()
                        ];
                    }),
                'error_rates' => [
                    'by_exercise_type' => Exercise::join('exercise_attempts', 'exercises.id', '=', 'exercise_attempts.exercise_id')
                        ->selectRaw('
                            exercises.type,
                            COUNT(*) as total_attempts,
                            SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) as error_count')
                        ->groupBy('type')
                        ->get()
                        ->map(function($stat) {
                            return [
                                'type' => $stat->type,
                                'error_rate' => $stat->total_attempts > 0 
                                    ? round(($stat->error_count / $stat->total_attempts) * 100, 2)
                                    : 0
                            ];
                        })
                ]
            ]
        ]);
    }
}
