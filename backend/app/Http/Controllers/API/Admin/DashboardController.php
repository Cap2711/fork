<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\LearningPath;
use App\Models\User;
use App\Models\UserProgress;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DashboardController extends BaseAPIController
{
    /**
     * Get summary statistics for the dashboard
     */
    public function summary(): JsonResponse
    {
        $now = Carbon::now();
        $thirtyDaysAgo = $now->copy()->subDays(30);

        // Active users in last 30 days
        $activeUsers = User::where('last_login_at', '>=', $thirtyDaysAgo)->count();

        // Content statistics
        $contentStats = [
            'learning_paths' => [
                'total' => LearningPath::count(),
                'published' => LearningPath::where('status', 'published')->count(),
                'draft' => LearningPath::where('status', 'draft')->count()
            ],
            'units' => DB::table('units')->count(),
            'lessons' => DB::table('lessons')->count(),
            'exercises' => DB::table('exercises')->count()
        ];

        // Progress statistics
        $progressStats = [
            'completed' => UserProgress::where('status', 'completed')
                ->where('updated_at', '>=', $thirtyDaysAgo)
                ->count(),
            'in_progress' => UserProgress::where('status', 'in_progress')
                ->where('updated_at', '>=', $thirtyDaysAgo)
                ->count(),
            'completion_rate' => $this->calculateCompletionRate($thirtyDaysAgo)
        ];

        // System statistics
        $systemStats = [
            'total_users' => User::count(),
            'active_users' => $activeUsers,
            'new_users' => User::where('created_at', '>=', $thirtyDaysAgo)->count(),
            'storage_used' => $this->calculateStorageUsed()
        ];

        return $this->sendResponse([
            'content_stats' => $contentStats,
            'progress_stats' => $progressStats,
            'system_stats' => $systemStats,
            'last_updated' => $now
        ]);
    }

    /**
     * Get recent activity for the dashboard
     */
    public function recentActivity(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);
        
        $activities = AuditLog::with('user')
            ->orderBy('performed_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'area' => $log->area,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name
                    ] : null,
                    'status' => $log->status,
                    'performed_at' => $log->performed_at,
                    'description' => $log->getDescription()
                ];
            });

        return $this->sendResponse($activities);
    }

    /**
     * Get detailed content statistics
     */
    public function contentStats(): JsonResponse
    {
        // Content creation over time
        $contentOverTime = $this->getContentCreationStats();

        // Most active content
        $mostActiveContent = $this->getMostActiveContent();

        // Content engagement metrics
        $engagementMetrics = $this->getEngagementMetrics();

        // Content completion rates
        $completionRates = $this->getCompletionRates();

        return $this->sendResponse([
            'content_over_time' => $contentOverTime,
            'most_active' => $mostActiveContent,
            'engagement_metrics' => $engagementMetrics,
            'completion_rates' => $completionRates
        ]);
    }

    /**
     * Calculate the overall completion rate since a given date
     */
    private function calculateCompletionRate(Carbon $since): float
    {
        $totalAttempts = UserProgress::where('created_at', '>=', $since)->count();
        
        if ($totalAttempts === 0) {
            return 0;
        }

        $completedAttempts = UserProgress::where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->count();

        return ($completedAttempts / $totalAttempts) * 100;
    }

    /**
     * Calculate total storage used by media files
     */
    private function calculateStorageUsed(): array
    {
        $totalSize = DB::table('media_files')->sum('size');
        
        return [
            'bytes' => $totalSize,
            'formatted' => $this->formatBytes($totalSize)
        ];
    }

    /**
     * Get content creation statistics over time
     */
    private function getContentCreationStats(): array
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        return [
            'learning_paths' => $this->getCreationTrend('learning_paths', $thirtyDaysAgo),
            'units' => $this->getCreationTrend('units', $thirtyDaysAgo),
            'lessons' => $this->getCreationTrend('lessons', $thirtyDaysAgo),
            'exercises' => $this->getCreationTrend('exercises', $thirtyDaysAgo)
        ];
    }

    /**
     * Get creation trend for a specific content type
     */
    private function getCreationTrend(string $table, Carbon $since): array
    {
        return DB::table($table)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', $since)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();
    }

    /**
     * Get most active content
     */
    private function getMostActiveContent(): array
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        return [
            'learning_paths' => $this->getMostActiveByType(
                'learning_paths',
                $thirtyDaysAgo
            ),
            'units' => $this->getMostActiveByType('units', $thirtyDaysAgo),
            'lessons' => $this->getMostActiveByType('lessons', $thirtyDaysAgo)
        ];
    }

    /**
     * Get most active content by type
     */
    private function getMostActiveByType(string $type, Carbon $since): array
    {
        return UserProgress::where('trackable_type', 'App\\Models\\' . Str::studly(Str::singular($type)))
            ->where('updated_at', '>=', $since)
            ->select('trackable_id', DB::raw('COUNT(*) as interactions'))
            ->groupBy('trackable_id')
            ->orderByDesc('interactions')
            ->limit(5)
            ->get()
            ->map(function ($progress) use ($type) {
                $model = 'App\\Models\\' . Str::studly(Str::singular($type));
                $content = $model::find($progress->trackable_id);
                return [
                    'id' => $progress->trackable_id,
                    'title' => $content?->title ?? 'Unknown',
                    'interactions' => $progress->interactions
                ];
            })
            ->toArray();
    }

    /**
     * Get engagement metrics
     */
    private function getEngagementMetrics(): array
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);

        return [
            'average_completion_time' => $this->getAverageCompletionTime($thirtyDaysAgo),
            'average_attempts' => $this->getAverageAttempts($thirtyDaysAgo),
            'user_retention' => $this->getUserRetention($thirtyDaysAgo)
        ];
    }

    /**
     * Get average completion time
     */
    private function getAverageCompletionTime(Carbon $since): array
    {
        $progress = UserProgress::where('status', 'completed')
            ->where('created_at', '>=', $since)
            ->whereNotNull('meta_data->time_spent')
            ->get();

        if ($progress->isEmpty()) {
            return ['average' => 0, 'count' => 0];
        }

        $totalTime = $progress->sum(function ($record) {
            return $record->meta_data['time_spent'] ?? 0;
        });

        return [
            'average' => $totalTime / $progress->count(),
            'count' => $progress->count()
        ];
    }

    /**
     * Get average attempts per completion
     */
    private function getAverageAttempts(Carbon $since): array
    {
        $progress = UserProgress::where('status', 'completed')
            ->where('created_at', '>=', $since)
            ->whereNotNull('meta_data->attempts')
            ->get();

        if ($progress->isEmpty()) {
            return ['average' => 0, 'count' => 0];
        }

        $totalAttempts = $progress->sum(function ($record) {
            return $record->meta_data['attempts'] ?? 0;
        });

        return [
            'average' => $totalAttempts / $progress->count(),
            'count' => $progress->count()
        ];
    }

    /**
     * Get user retention metrics
     */
    private function getUserRetention(Carbon $since): array
    {
        $totalUsers = User::where('created_at', '>=', $since)->count();
        
        if ($totalUsers === 0) {
            return ['rate' => 0, 'active_users' => 0, 'total_users' => 0];
        }

        $activeUsers = User::where('created_at', '>=', $since)
            ->where('last_login_at', '>=', Carbon::now()->subDays(7))
            ->count();

        return [
            'rate' => ($activeUsers / $totalUsers) * 100,
            'active_users' => $activeUsers,
            'total_users' => $totalUsers
        ];
    }

    /**
     * Get completion rates for different content types
     */
    private function getCompletionRates(): array
    {
        $types = ['learning_paths', 'units', 'lessons', 'exercises'];
        $rates = [];

        foreach ($types as $type) {
            $rates[$type] = $this->getCompletionRateByType($type);
        }

        return $rates;
    }

    /**
     * Get completion rate for a specific content type
     */
    private function getCompletionRateByType(string $type): array
    {
        $progress = UserProgress::where('trackable_type', 'App\\Models\\' . Str::studly(Str::singular($type)))
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($progress);
        
        if ($total === 0) {
            return ['rate' => 0, 'completed' => 0, 'total' => 0];
        }

        $completed = $progress['completed'] ?? 0;

        return [
            'rate' => ($completed / $total) * 100,
            'completed' => $completed,
            'total' => $total
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}