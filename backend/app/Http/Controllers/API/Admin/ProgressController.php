<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\User;
use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Lesson;
use App\Models\Progress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgressController extends BaseAPIController
{
    /**
     * Map of content types to their model classes
     */
    protected $contentTypeMap = [
        'learning-paths' => 'App\\Models\\LearningPath',
        'units' => 'App\\Models\\Unit',
        'lessons' => 'App\\Models\\Lesson',
        'sections' => 'App\\Models\\Section',
        'exercises' => 'App\\Models\\Exercise',
        'quizzes' => 'App\\Models\\Quiz',
    ];

    /**
     * Get an overview of user progress statistics
     */
    public function overview(): JsonResponse
    {
        // Get total active users
        $totalUsers = User::where('status', 'active')->count();
        
        // Get users who started at least one learning path
        $activeUsers = DB::table('progress')
            ->select('user_id')
            ->distinct()
            ->count('user_id');
        
        // Get completion statistics for learning paths
        $learningPathStats = DB::table('progress')
            ->select('content_type', 'status', DB::raw('count(*) as count'))
            ->where('content_type', 'App\\Models\\LearningPath')
            ->groupBy('content_type', 'status')
            ->get()
            ->groupBy('status')
            ->map(function ($item) {
                return $item->first()->count;
            });
        
        // Get completion statistics for units
        $unitStats = DB::table('progress')
            ->select('content_type', 'status', DB::raw('count(*) as count'))
            ->where('content_type', 'App\\Models\\Unit')
            ->groupBy('content_type', 'status')
            ->get()
            ->groupBy('status')
            ->map(function ($item) {
                return $item->first()->count;
            });
        
        // Get completion statistics for lessons
        $lessonStats = DB::table('progress')
            ->select('content_type', 'status', DB::raw('count(*) as count'))
            ->where('content_type', 'App\\Models\\Lesson')
            ->groupBy('content_type', 'status')
            ->get()
            ->groupBy('status')
            ->map(function ($item) {
                return $item->first()->count;
            });
        
        // Get recent activity
        $recentActivity = DB::table('progress')
            ->join('users', 'progress.user_id', '=', 'users.id')
            ->select('progress.*', 'users.name as user_name', 'users.email as user_email')
            ->orderBy('progress.updated_at', 'desc')
            ->limit(20)
            ->get();
        
        return $this->sendResponse([
            'user_stats' => [
                'total_users' => $totalUsers,
                'active_users' => $activeUsers,
                'completion_rate' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 2) : 0
            ],
            'learning_path_stats' => $learningPathStats,
            'unit_stats' => $unitStats,
            'lesson_stats' => $lessonStats,
            'recent_activity' => $recentActivity
        ]);
    }
    
    /**
     * Get progress details for a specific user
     */
    public function userProgress(User $user): JsonResponse
    {
        // Get all learning paths with progress
        $learningPaths = LearningPath::with(['progress' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }])->get();
        
        // Get all units with progress
        $units = Unit::with(['progress' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }])->get();
        
        // Get all lessons with progress
        $lessons = Lesson::with(['progress' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }])->get();
        
        // Calculate overall completion percentage
        $totalContent = $learningPaths->count() + $units->count() + $lessons->count();
        $completedContent = 
            $learningPaths->filter(function ($path) {
                return $path->progress->isNotEmpty() && $path->progress->first()->status === 'completed';
            })->count() +
            $units->filter(function ($unit) {
                return $unit->progress->isNotEmpty() && $unit->progress->first()->status === 'completed';
            })->count() +
            $lessons->filter(function ($lesson) {
                return $lesson->progress->isNotEmpty() && $lesson->progress->first()->status === 'completed';
            })->count();
        
        $completionPercentage = $totalContent > 0 ? round(($completedContent / $totalContent) * 100, 2) : 0;
        
        // Get recent activity for this user
        $recentActivity = Progress::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->get();
        
        return $this->sendResponse([
            'user' => $user,
            'completion_percentage' => $completionPercentage,
            'learning_paths' => $learningPaths->map(function ($path) {
                return [
                    'id' => $path->id,
                    'title' => $path->title,
                    'status' => $path->progress->isNotEmpty() ? $path->progress->first()->status : 'not_started',
                    'last_activity' => $path->progress->isNotEmpty() ? $path->progress->first()->updated_at : null
                ];
            }),
            'units' => $units->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'title' => $unit->title,
                    'status' => $unit->progress->isNotEmpty() ? $unit->progress->first()->status : 'not_started',
                    'last_activity' => $unit->progress->isNotEmpty() ? $unit->progress->first()->updated_at : null
                ];
            }),
            'lessons' => $lessons->map(function ($lesson) {
                return [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'status' => $lesson->progress->isNotEmpty() ? $lesson->progress->first()->status : 'not_started',
                    'last_activity' => $lesson->progress->isNotEmpty() ? $lesson->progress->first()->updated_at : null
                ];
            }),
            'recent_activity' => $recentActivity
        ]);
    }
    
    /**
     * Get progress details for a specific content item
     */
    public function contentProgress(string $type, int $id): JsonResponse
    {
        if (!array_key_exists($type, $this->contentTypeMap)) {
            return $this->sendError('Invalid content type.', 400);
        }
        
        $modelClass = $this->contentTypeMap[$type];
        $content = $modelClass::findOrFail($id);
        
        // Get all progress records for this content
        $progress = Progress::where('content_type', get_class($content))
            ->where('content_id', $content->id)
            ->with('user')
            ->get();
        
        // Calculate statistics
        $totalUsers = User::where('status', 'active')->count();
        $usersStarted = $progress->count();
        $usersCompleted = $progress->where('status', 'completed')->count();
        
        $startRate = $totalUsers > 0 ? round(($usersStarted / $totalUsers) * 100, 2) : 0;
        $completionRate = $usersStarted > 0 ? round(($usersCompleted / $usersStarted) * 100, 2) : 0;
        
        // Group progress by status
        $progressByStatus = $progress->groupBy('status')
            ->map(function ($items, $status) {
                return [
                    'status' => $status,
                    'count' => $items->count()
                ];
            })->values();
        
        return $this->sendResponse([
            'content' => $content,
            'total_users' => $totalUsers,
            'users_started' => $usersStarted,
            'users_completed' => $usersCompleted,
            'start_rate' => $startRate,
            'completion_rate' => $completionRate,
            'progress_by_status' => $progressByStatus,
            'user_progress' => $progress->map(function ($item) {
                return [
                    'user_id' => $item->user_id,
                    'user_name' => $item->user->name,
                    'user_email' => $item->user->email,
                    'status' => $item->status,
                    'score' => $item->score,
                    'last_activity' => $item->updated_at
                ];
            })
        ]);
    }
}
