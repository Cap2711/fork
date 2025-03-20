<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\LearningPath;
use App\Models\Unit;
use App\Models\Lesson;
use App\Models\UserProgress;
use App\Models\UserStreak;
use App\Models\XpHistory;
use App\Models\Language;
use App\Models\Achievement;
use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminDashboardAnalyticsTest extends AdminTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestData();
    }

    protected function createTestData(): void
    {
        // Create test languages
        $japanese = Language::create([
            'name' => 'Japanese',
            'code' => 'ja',
            'status' => 'active'
        ]);

        // Create learning path structure
        $learningPath = LearningPath::create([
            'title' => 'Japanese 101',
            'description' => 'Learn basic Japanese',
            'target_level' => 'beginner',
            'status' => 'published',
            'language_id' => $japanese->id
        ]);

        $unit = Unit::create([
            'learning_path_id' => $learningPath->id,
            'title' => 'Unit 1',
            'description' => 'Basic greetings',
            'order' => 1,
            'status' => 'published'
        ]);

        $lesson = Lesson::create([
            'unit_id' => $unit->id,
            'title' => 'Greetings',
            'description' => 'Learn common greetings',
            'order' => 1,
            'status' => 'published'
        ]);

        // Create test exercises
        $exercise = Exercise::create([
            'section_id' => 1,
            'type' => 'multiple_choice',
            'content' => ['question' => 'Test question'],
            'answers' => ['correct' => 'A'],
            'order' => 1
        ]);

        // Create test quiz
        $quiz = Quiz::create([
            'unit_id' => $unit->id,
            'title' => 'Unit 1 Quiz',
            'passing_score' => 70,
            'status' => 'published'
        ]);

        // Create test users with progress and streaks
        for ($i = 1; $i <= 5; $i++) {
            $user = User::create([
                'name' => "Test User $i",
                'email' => "user$i@example.com",
                'password' => bcrypt('password'),
                'role' => 'user'
            ]);

            // Add progress
            UserProgress::create([
                'user_id' => $user->id,
                'trackable_type' => Lesson::class,
                'trackable_id' => $lesson->id,
                'status' => $i <= 3 ? 'completed' : 'in_progress',
                'xp_earned' => $i <= 3 ? 20 : 10
            ]);

            // Add streaks
            UserStreak::create([
                'user_id' => $user->id,
                'current_streak' => $i,
                'longest_streak' => $i + 2,
                'last_activity_date' => now()->subDays(1)
            ]);

            // Add XP history
            XpHistory::create([
                'user_id' => $user->id,
                'amount' => $i * 10,
                'source' => 'lesson_completion',
                'created_at' => now()->subDays($i)
            ]);

            // Add exercise attempts
            ExerciseAttempt::create([
                'exercise_id' => $exercise->id,
                'user_id' => $user->id,
                'is_correct' => $i % 2 == 0,
                'user_answer' => $i % 2 == 0 ? 'A' : 'B',
                'time_taken_seconds' => 30 + $i
            ]);

            // Add quiz attempts
            QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'answers' => ['q1' => 'A'],
                'score' => 60 + ($i * 5),
                'passed' => 60 + ($i * 5) >= 70,
                'time_taken_seconds' => 300 + ($i * 60),
                'question_results' => [
                    ['question_id' => 1, 'correct' => true],
                    ['question_id' => 2, 'correct' => $i % 2 == 0]
                ]
            ]);
        }

        // Create some achievements
        Achievement::create([
            'name' => 'First Lesson',
            'description' => 'Complete your first lesson',
            'type' => 'progress',
            'requirement' => 1
        ]);
    }

    public function test_dashboard_shows_user_engagement_metrics()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard/engagement');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'daily_active_users' => 5,
                    'streak_statistics' => [
                        'users_with_streaks' => 5,
                        'average_streak_length' => true
                    ],
                    'retention_rate' => true
                ]
            ]);
    }

    public function test_dashboard_shows_learning_progress()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard/progress');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'completion_rates' => [
                        'overall' => 60.0, // 3 out of 5 users completed
                        'by_language' => [
                            'Japanese' => 60.0
                        ]
                    ],
                    'popular_content' => [
                        'lessons' => true,
                        'units' => true
                    ],
                    'learning_metrics' => [
                        'exercise_success_rate' => 40.0, // 2 out of 5 attempts correct
                        'quiz_pass_rate' => 60.0,  // 3 out of 5 attempts passed
                        'average_completion_time' => true
                    ]
                ]
            ]);
    }

    public function test_dashboard_shows_achievements_overview()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard/achievements');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_achievements' => 1,
                    'completion_rates' => true,
                    'top_achievers' => true
                ]
            ]);
    }

    public function test_dashboard_shows_xp_leaderboards()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard/leaderboards');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'daily_leaders' => [
                        '*' => ['user', 'xp']
                    ],
                    'weekly_leaders' => [
                        '*' => ['user', 'xp']
                    ],
                    'all_time_leaders' => [
                        '*' => ['user', 'xp']
                    ]
                ]
            ]);
    }

    public function test_dashboard_shows_content_health()
    {
        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/dashboard/content-health');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'needs_review' => [
                        'lessons',
                        'exercises' => [
                            '*' => [
                                'id',
                                'type',
                                'success_rate',
                                'average_time'
                            ]
                        ],
                        'quizzes' => [
                            '*' => [
                                'id',
                                'title',
                                'pass_rate',
                                'average_score'
                            ]
                        ]
                    ],
                    'performance_metrics' => [
                        'exercises' => [
                            'completion_rate',
                            'average_attempts',
                            'success_rate'
                        ],
                        'quizzes' => [
                            'completion_rate',
                            'average_score',
                            'time_distribution'
                        ]
                    ],
                    'user_feedback'
                ]
            ]);
    }

    public function test_unauthorized_user_cannot_access_dashboard()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/dashboard/engagement');

        $this->assertUnauthorized($response);
    }
}
