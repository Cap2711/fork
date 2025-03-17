<?php

namespace Tests\Feature\Admin;

use App\Models\Achievement;
use App\Models\League;
use App\Models\XpRule;
use App\Models\StreakRule;
use App\Models\DailyGoal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class GamificationTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_admin_can_create_achievement()
    {
        $icon = UploadedFile::fake()->image('achievement.svg');

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/gamification/achievements', [
                'name' => 'Speed Demon',
                'description' => 'Complete 5 perfect lessons in a row',
                'requirements' => [
                    'type' => 'perfect_lessons',
                    'count' => 5,
                    'consecutive' => true
                ],
                'rewards' => [
                    'xp' => 100,
                    'gems' => 50
                ],
                'icon' => $icon
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Speed Demon',
                    'requirements' => [
                        'type' => 'perfect_lessons',
                        'count' => 5
                    ]
                ]
            ]);

        $this->assertDatabaseHas('achievements', [
            'name' => 'Speed Demon',
            'rewards->xp' => 100,
            'rewards->gems' => 50
        ]);
    }

    public function test_admin_can_update_achievement()
    {
        $achievement = Achievement::create([
            'name' => 'Early Bird',
            'description' => 'Complete a lesson before 7 AM',
            'requirements' => [
                'type' => 'time_based',
                'before_hour' => 7
            ],
            'rewards' => [
                'xp' => 20,
                'gems' => 10
            ]
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/gamification/achievements/{$achievement->id}", [
                'name' => 'Morning Champion',
                'rewards' => [
                    'xp' => 30,
                    'gems' => 15
                ]
            ]);

        $response->assertOk();
        
        $this->assertDatabaseHas('achievements', [
            'id' => $achievement->id,
            'name' => 'Morning Champion',
            'rewards->xp' => 30,
            'rewards->gems' => 15
        ]);
    }

    public function test_admin_can_configure_xp_rules()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/gamification/xp-rules', [
                'rules' => [
                    [
                        'action' => 'lesson_completion',
                        'base_xp' => 10,
                        'multipliers' => [
                            'perfect_score' => 1.5,
                            'streak_bonus' => 1.2,
                            'weekend_bonus' => 2.0
                        ]
                    ],
                    [
                        'action' => 'practice_session',
                        'base_xp' => 5,
                        'multipliers' => [
                            'difficulty' => [
                                'easy' => 1.0,
                                'medium' => 1.2,
                                'hard' => 1.5
                            ]
                        ]
                    ]
                ]
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'rules_count' => 2
                ]
            ]);

        $this->assertDatabaseCount('xp_rules', 2);
    }

    public function test_admin_can_manage_league_tiers()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/gamification/leagues', [
                'leagues' => [
                    [
                        'name' => 'Bronze',
                        'tier' => 1,
                        'requirements' => [
                            'min_xp' => 0,
                            'promotion_rank' => 10
                        ],
                        'rewards' => [
                            'weekly_gems' => 5
                        ]
                    ],
                    [
                        'name' => 'Diamond',
                        'tier' => 5,
                        'requirements' => [
                            'min_xp' => 10000,
                            'promotion_rank' => 3
                        ],
                        'rewards' => [
                            'weekly_gems' => 50
                        ]
                    ]
                ]
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'leagues_count' => 2
                ]
            ]);

        $this->assertDatabaseHas('leagues', [
            'name' => 'Bronze',
            'tier' => 1
        ]);
    }

    public function test_admin_can_configure_streak_settings()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/gamification/streak-rules', [
                'freeze_cost' => 10,
                'repair_window_hours' => 48,
                'bonus_schedule' => [
                    ['days' => 5, 'gems' => 5],
                    ['days' => 10, 'gems' => 10],
                    ['days' => 30, 'gems' => 50]
                ],
                'xp_multipliers' => [
                    ['days' => 7, 'multiplier' => 1.1],
                    ['days' => 30, 'multiplier' => 1.25]
                ]
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'freeze_cost' => 10,
                    'repair_window_hours' => 48
                ]
            ]);

        $this->assertDatabaseHas('streak_rules', [
            'freeze_cost' => 10,
            'repair_window_hours' => 48
        ]);
    }

    public function test_admin_can_manage_daily_goals()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/gamification/daily-goals', [
                'options' => [
                    [
                        'xp_target' => 20,
                        'name' => 'Casual',
                        'rewards' => [
                            'streak_points' => 1
                        ]
                    ],
                    [
                        'xp_target' => 50,
                        'name' => 'Serious',
                        'rewards' => [
                            'streak_points' => 2,
                            'bonus_gems' => 1
                        ]
                    ],
                    [
                        'xp_target' => 100,
                        'name' => 'Intense',
                        'rewards' => [
                            'streak_points' => 3,
                            'bonus_gems' => 2
                        ]
                    ]
                ]
            ]);

        $response->assertOk();
        $this->assertDatabaseCount('daily_goals', 3);
        
        $this->assertDatabaseHas('daily_goals', [
            'name' => 'Casual',
            'xp_target' => 20
        ]);
    }

    public function test_admin_can_manage_bonus_events()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/gamification/bonus-events', [
                'name' => 'Weekend Warriors',
                'description' => 'Double XP on all weekend activities',
                'start_date' => '2025-04-01',
                'end_date' => '2025-04-03',
                'bonuses' => [
                    'xp_multiplier' => 2.0,
                    'gems_multiplier' => 1.5
                ],
                'conditions' => [
                    'days_of_week' => [6, 7], // Saturday and Sunday
                    'min_level' => 1
                ]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Weekend Warriors',
                    'bonuses' => [
                        'xp_multiplier' => 2.0
                    ]
                ]
            ]);
    }
}