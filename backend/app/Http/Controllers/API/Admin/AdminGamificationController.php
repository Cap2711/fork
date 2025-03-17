<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\League;
use App\Models\XpRule;
use App\Models\StreakRule;
use App\Models\DailyGoal;
use App\Models\BonusEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Admin\Gamification\{
    CreateAchievementRequest,
    UpdateAchievementRequest,
    CreateLeagueRequest,
    UpdateStreakRulesRequest,
    CreateDailyGoalRequest,
    CreateBonusEventRequest
};

class AdminGamificationController extends Controller
{
    /**
     * Create a new achievement.
     */
    public function createAchievement(CreateAchievementRequest $request)
    {
        $achievement = DB::transaction(function () use ($request) {
            $achievement = Achievement::create($request->validated());

            if ($request->hasFile('icon')) {
                $achievement->addMedia($request->file('icon'))
                    ->toMediaCollection('icon');
            }

            return $achievement;
        });

        return response()->json([
            'success' => true,
            'data' => $achievement->getPreviewData()
        ], 201);
    }

    /**
     * Update an achievement.
     */
    public function updateAchievement(UpdateAchievementRequest $request, Achievement $achievement)
    {
        $achievement = DB::transaction(function () use ($request, $achievement) {
            $achievement->update($request->validated());

            if ($request->hasFile('icon')) {
                $achievement->clearMediaCollection('icon');
                $achievement->addMedia($request->file('icon'))
                    ->toMediaCollection('icon');
            }

            return $achievement;
        });

        return response()->json([
            'success' => true,
            'data' => $achievement->getPreviewData()
        ]);
    }

    /**
     * Configure XP rules.
     */
    public function configureXpRules(Request $request)
    {
        $request->validate([
            'rules' => 'required|array',
            'rules.*.action' => 'required|string',
            'rules.*.base_xp' => 'required|integer|min:0',
            'rules.*.multipliers' => 'array'
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->rules as $rule) {
                XpRule::updateOrCreate(
                    ['action' => $rule['action']],
                    [
                        'base_xp' => $rule['base_xp'],
                        'multipliers' => $rule['multipliers'] ?? null
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'data' => [
                'rules_count' => count($request->rules)
            ]
        ]);
    }

    /**
     * Configure leagues.
     */
    public function configureLeagues(CreateLeagueRequest $request)
    {
        $leagues = DB::transaction(function () use ($request) {
            $leagues = collect($request->leagues)->map(function ($leagueData) {
                return League::create([
                    'name' => $leagueData['name'],
                    'tier' => $leagueData['tier'],
                    'requirements' => $leagueData['requirements'],
                    'rewards' => $leagueData['rewards']
                ]);
            });

            return $leagues;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'leagues_count' => $leagues->count(),
                'leagues' => $leagues
            ]
        ]);
    }

    /**
     * Configure streak rules.
     */
    public function configureStreakRules(UpdateStreakRulesRequest $request)
    {
        $rules = StreakRule::updateOrCreate(
            ['id' => 1], // Single configuration record
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'data' => $rules
        ]);
    }

    /**
     * Configure daily goals.
     */
    public function configureDailyGoals(CreateDailyGoalRequest $request)
    {
        DB::transaction(function () use ($request) {
            // Clear existing goals if we're replacing them
            if ($request->boolean('replace_existing', false)) {
                DailyGoal::query()->delete();
            }

            foreach ($request->options as $option) {
                DailyGoal::create([
                    'name' => $option['name'],
                    'xp_target' => $option['xp_target'],
                    'rewards' => $option['rewards']
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => DailyGoal::all()
        ]);
    }

    /**
     * Create a bonus event.
     */
    public function createBonusEvent(CreateBonusEventRequest $request)
    {
        $event = BonusEvent::create($request->validated());

        return response()->json([
            'success' => true,
            'data' => $event
        ], 201);
    }

    /**
     * Get gamification statistics.
     */
    public function getStatistics()
    {
        $stats = [
            'achievements' => [
                'total' => Achievement::count(),
                'most_earned' => Achievement::withCount('users')
                    ->orderBy('users_count', 'desc')
                    ->first()
            ],
            'leagues' => [
                'active_users' => DB::table('league_memberships')
                    ->where('joined_at', '>=', now()->subWeek())
                    ->count(),
                'distribution' => League::withCount('users')->get()
            ],
            'streaks' => [
                'active_streaks' => DB::table('user_streaks')
                    ->where('current_streak', '>', 0)
                    ->count(),
                'longest_current' => DB::table('user_streaks')
                    ->max('current_streak')
            ],
            'daily_goals' => [
                'completion_rate' => DB::table('user_daily_goals')
                    ->where('date', '>=', now()->subDays(30))
                    ->avg('completed')
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}