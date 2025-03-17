<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Lesson;
use App\Models\UserProgress;

class DashboardAnalyticsTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create some test data
        $this->createTestData();
    }

    protected function createTestData(): void
    {
        // Create learning path structure
        $learningPath = LearningPath::create([
            'title' => 'Japanese 101',
            'status' => 'published'
        ]);

        $unit = Unit::create([
            'learning_path_id' => $learningPath->id,
            'title' => 'Unit 1',
            'status' => 'published'
        ]);

        $lesson = Lesson::create([
            'unit_id' => $unit->id,
            'title' => 'Lesson 1',
            'status' => 'published'
        ]);

        // Create test users with progress
        for ($i = 1; $i <= 5; $i++) {
            $user = User::create([
                'name' => "Test User $i",
                'email' => "user$i@example.com",
                'password' => bcrypt('password'),
                'role' => 'user'
            ]);

            // Add some progress
            UserProgress::create([
                'user_id' => $user->id,
                'trackable_type' => Lesson::class,
                'trackable_id' => $lesson->id,
                'status' => $i <= 3 ? 'completed' : 'in_progress'
            ]);
        }
    }

    public function test_admin_can_access_dashboard()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_users' => 6, // 5 test users + 1 admin
                    'total_learning_paths' => 1,
                    'total_active_users' => 5,
                    'recent_activities' => true // Assert presence only
                ]
            ]);
    }

    public function test_unauthorized_user_cannot_access_dashboard()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/dashboard');

        $this->assertUnauthorized($response);
    }

    public function test_admin_can_access_analytics()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/analytics');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_statistics' => [
                        'total_registered' => 6,
                        'active_last_week' => true, // Assert presence
                        'completion_rates' => true
                    ],
                    'content_statistics' => [
                        'total_learning_paths' => 1,
                        'total_lessons' => 1,
                        'published_content' => [
                            'learning_paths' => 1,
                            'units' => 1,
                            'lessons' => 1
                        ]
                    ],
                    'learning_statistics' => [
                        'average_completion_time' => true,
                        'popular_content' => true,
                        'completion_rates' => [
                            'lessons' => true
                        ]
                    ]
                ]
            ]);
    }

    public function test_analytics_date_range_filter()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/analytics?from=2025-01-01&to=2025-12-31');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user_statistics',
                    'content_statistics',
                    'learning_statistics',
                    'date_range' => ['from', 'to']
                ]
            ]);
    }

    public function test_dashboard_shows_recent_activities()
    {
        // Create some recent activities
        $user = User::first();
        UserProgress::create([
            'user_id' => $user->id,
            'trackable_type' => Lesson::class,
            'trackable_id' => Lesson::first()->id,
            'status' => 'completed',
            'created_at' => now()
        ]);

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'recent_activities' => [
                        '*' => [
                            'id',
                            'user',
                            'activity_type',
                            'content_type',
                            'content_title',
                            'timestamp'
                        ]
                    ]
                ]
            ]);
    }

    public function test_analytics_provides_user_engagement_metrics()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/analytics?metrics=user_engagement');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'engagement_metrics' => [
                        'daily_active_users',
                        'weekly_active_users',
                        'monthly_active_users',
                        'average_session_duration',
                        'retention_rates' => [
                            'day_1',
                            'day_7',
                            'day_30'
                        ]
                    ]
                ]
            ]);
    }
}