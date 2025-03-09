<?php

namespace App\Services\Admin;

use App\Models\Unit;
use App\Models\User;
use App\Models\UserUnitProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UnitService
{
    public function list(): Collection
    {
        return Unit::with(['lessons' => function ($query) {
            $query->withCount(['exercises']);
        }])
        ->orderBy('order')
        ->get()
        ->map(function ($unit) {
            return $this->attachStats($unit);
        });
    }

    public function create(array $data): Unit
    {
        return DB::transaction(function () use ($data) {
            // Reorder existing units if necessary
            if (Unit::where('order', '>=', $data['order'])->exists()) {
                Unit::where('order', '>=', $data['order'])
                    ->increment('order');
            }

            return Unit::create($data);
        });
    }

    public function update(Unit $unit, array $data): Unit
    {
        return DB::transaction(function () use ($unit, $data) {
            if (isset($data['order']) && $data['order'] !== $unit->order) {
                $this->reorderUnits($unit, $data['order']);
            }

            $unit->update($data);
            return $unit->fresh();
        });
    }

    public function delete(Unit $unit): void
    {
        DB::transaction(function () use ($unit) {
            // Update order of remaining units
            Unit::where('order', '>', $unit->order)
                ->decrement('order');
            
            $unit->delete();
        });
    }

    public function attachStats(Unit $unit): array
    {
        $totalUsers = User::where('role', '!=', 'admin')->count();
        $completedUsers = UserUnitProgress::where('unit_id', $unit->id)
            ->where('level', '>', 0)
            ->count();

        return array_merge($unit->toArray(), [
            'completion_rate' => $totalUsers > 0 ? 
                round(($completedUsers / $totalUsers) * 100, 2) : 0,
            'avg_completion_time' => $this->calculateAverageCompletionTime($unit),
            'total_users' => $totalUsers,
            'completed_users' => $completedUsers,
        ]);
    }

    private function reorderUnits(Unit $unit, int $newOrder): void
    {
        if ($newOrder > $unit->order) {
            Unit::whereBetween('order', [$unit->order + 1, $newOrder])
                ->decrement('order');
        } else {
            Unit::whereBetween('order', [$newOrder, $unit->order - 1])
                ->increment('order');
        }
        $unit->update(['order' => $newOrder]);
    }

    private function calculateAverageCompletionTime(Unit $unit): ?float
    {
        return UserUnitProgress::where('unit_id', $unit->id)
            ->whereNotNull('completed_at')
            ->avg(DB::raw('TIMESTAMPDIFF(SECOND, created_at, completed_at)'));
    }
}