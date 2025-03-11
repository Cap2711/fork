<?php
namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\LearningPath;
use App\Models\User;
use App\Models\UserProgress;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends BaseAPIController
{
    /**
     * Get user engagement metrics with detailed learning behavior analysis.
     * Unlike DashboardController's summary, this provides in-depth behavioral insights.
     */
    public function userEngagement(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'interval'   => 'nullable|string|in:daily,weekly,monthly',
        ]);

        $startDate = Carbon::parse($request->input('start_date', Carbon::now()->subDays(30)));
        $endDate   = Carbon::parse($request->input('end_date', Carbon::now()));

        $userEngagement = UserProgress::with('user')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                'user_id',
                DB::raw('COUNT(*) as total_interactions'),
                DB::raw('AVG(meta_data->>"$.time_spent") as avg_time_spent'),
                DB::raw('COUNT(DISTINCT DATE(created_at)) as active_days'),
                DB::raw('MAX(created_at) as last_activity')
            )
            ->groupBy('user_id')
            ->get()
            ->map(function ($record) use ($startDate, $endDate) {
                $daysSinceStart = $startDate->diffInDays($endDate);
                return [
                    'user'                     => $record->user->name,
                    'total_interactions'       => $record->total_interactions,
                    'avg_time_per_session'     => round($record->avg_time_spent / 60, 2), // minutes
                    'engagement_rate'          => round(($record->active_days / $daysSinceStart) * 100, 2),
                    'days_since_last_activity' => Carbon::parse($record->last_activity)->diffInDays(now()),
                ];
            });

        return $this->sendResponse([
            'engagement_metrics' => $userEngagement,
            'retention_analysis' => $this->calculateRetentionRates($startDate, $endDate),
            'learning_patterns'  => $this->analyzeLearningPatterns($startDate, $endDate),
        ]);
    }

    /**
     * Get detailed content performance metrics with statistical analysis.
     * Provides deeper insights than DashboardController's basic content stats.
     */
    public function contentPerformance(Request $request): JsonResponse
    {
        $request->validate([
            'content_type' => 'nullable|string|in:learning_paths,units,lessons,exercises',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->input('start_date', Carbon::now()->subDays(30)));
        $endDate   = Carbon::parse($request->input('end_date', Carbon::now()));

        $contentType = $request->input('content_type', 'learning_paths');
        $modelClass  = 'App\\Models\\' . ucfirst(Str::singular($contentType));

        $performance = DB::table($contentType)
            ->leftJoin('user_progress', function ($join) use ($contentType) {
                $join->on($contentType . '.id', '=', 'user_progress.trackable_id')
                    ->where('user_progress.trackable_type', '=', $modelClass);
            })
            ->whereBetween('user_progress.created_at', [$startDate, $endDate])
            ->select(
                $contentType . '.id',
                $contentType . '.title',
                DB::raw('COUNT(DISTINCT user_progress.user_id) as total_users'),
                DB::raw('COUNT(CASE WHEN user_progress.status = "completed" THEN 1 END) as completions'),
                DB::raw('AVG(JSON_EXTRACT(user_progress.meta_data, "$.time_spent")) as avg_time_spent'),
                DB::raw('AVG(JSON_EXTRACT(user_progress.meta_data, "$.score")) as avg_score')
            )
            ->groupBy($contentType . '.id', $contentType . '.title')
            ->get()
            ->map(function ($record) {
                return [
                    'id'               => $record->id,
                    'title'            => $record->title,
                    'total_users'      => $record->total_users,
                    'completion_rate'  => $record->total_users > 0 ?
                    round(($record->completions / $record->total_users) * 100, 2) : 0,
                    'avg_time_spent'   => round($record->avg_time_spent / 60, 2), // minutes
                    'avg_score'        => round($record->avg_score, 2),
                    'engagement_level' => $this->calculateEngagementLevel($record),
                ];
            });

        return $this->sendResponse([
            'content_performance' => $performance,
            'difficulty_analysis' => $this->analyzeDifficulty($contentType, $startDate, $endDate),
            'progression_paths'   => $this->analyzeProgressionPaths($contentType),
        ]);
    }

    /**
     * Get comprehensive learning progress analytics.
     * Provides more detailed progress tracking than DashboardController.
     */
    public function learningProgress(Request $request): JsonResponse
    {
        $request->validate([
            'learning_path_id' => 'nullable|exists:learning_paths,id',
            'user_group'       => 'nullable|string',
        ]);

        $query = UserProgress::with(['user', 'trackable'])
            ->when($request->learning_path_id, function ($q) use ($request) {
                $q->whereHasMorph('trackable', [LearningPath::class], function ($q) use ($request) {
                    $q->where('id', $request->learning_path_id);
                });
            });

        $progress = $query->get()->groupBy('user_id')->map(function ($userProgress) {
            $user = $userProgress->first()->user;
            return [
                'user_id'           => $user->id,
                'name'              => $user->name,
                'completed_items'   => $userProgress->where('status', 'completed')->count(),
                'total_items'       => $userProgress->count(),
                'completion_rate'   => round(
                    ($userProgress->where('status', 'completed')->count() / $userProgress->count()) * 100,
                    2
                ),
                'avg_score'         => round($userProgress->avg('meta_data.score'), 2),
                'total_time_spent'  => round($userProgress->sum('meta_data.time_spent') / 3600, 2), // hours
                'learning_velocity' => $this->calculateLearningVelocity($userProgress),
            ];
        });

        return $this->sendResponse([
            'progress_metrics'          => $progress,
            'skill_progression'         => $this->analyzeSkillProgression(),
            'learning_paths_efficiency' => $this->analyzeLearningPathsEfficiency(),
        ]);
    }

    /**
     * Calculate retention rates with cohort analysis
     */
    private function calculateRetentionRates(Carbon $startDate, Carbon $endDate): array
    {
        $cohorts = User::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as cohort'),
                DB::raw('COUNT(*) as total_users')
            )
            ->groupBy('cohort')
            ->get();

        foreach ($cohorts as &$cohort) {
            $retentionByWeek = [];
            $cohortDate      = Carbon::createFromFormat('Y-m', $cohort->cohort);

            for ($week = 1; $week <= 8; $week++) {
                $weekStart = $cohortDate->copy()->addWeeks($week - 1);
                $weekEnd   = $weekStart->copy()->addWeek();

                $activeUsers = UserProgress::whereHas('user', function ($query) use ($cohort) {
                    $query->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$cohort->cohort]);
                })
                    ->whereBetween('created_at', [$weekStart, $weekEnd])
                    ->distinct('user_id')
                    ->count();

                $retentionByWeek["week_{$week}"] = [
                    'active_users'   => $activeUsers,
                    'retention_rate' => round(($activeUsers / $cohort->total_users) * 100, 2),
                ];
            }

            $cohort->retention = $retentionByWeek;
        }

        return $cohorts->toArray();
    }

    /**
     * Analyze learning patterns
     */
    private function analyzeLearningPatterns(Carbon $startDate, Carbon $endDate): array
    {
        $patterns = UserProgress::whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('DAYOFWEEK(created_at) as day'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('hour', 'day')
            ->get();

        $heatmap = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($patterns as $pattern) {
            $heatmap[$pattern->day - 1][$pattern->hour] = $pattern->count;
        }

        return [
            'activity_heatmap'     => $heatmap,
            'peak_hours'           => $this->findPeakHours($patterns),
            'weekday_distribution' => $this->calculateWeekdayDistribution($patterns),
        ];
    }

    /**
     * Calculate engagement level
     */
    private function calculateEngagementLevel($record): string
    {
        $score = 0;

        // Weight different factors
        $score += ($record->completion_rate * 0.4);
        $score += (min($record->avg_time_spent, 60) / 60 * 30); // Cap at 60 minutes
        $score += ($record->avg_score ?? 0) * 0.3;

        return match (true) {
            $score >= 80 => 'High',
            $score >= 50 => 'Medium',
            default => 'Low'
        };
    }

    /**
     * Calculate learning velocity (items completed per week)
     */
    private function calculateLearningVelocity($userProgress): float
    {
        $dateRange = Carbon::parse($userProgress->min('created_at'))
            ->diffInWeeks(Carbon::parse($userProgress->max('created_at'))) ?: 1;

        return round($userProgress->where('status', 'completed')->count() / $dateRange, 2);
    }
}