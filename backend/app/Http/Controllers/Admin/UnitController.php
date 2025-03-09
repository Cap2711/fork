<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\Admin\UnitService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    public function __construct(
        private UnitService $unitService
    ) {}

    /**
     * Get all units with their lessons and stats
     */
    public function index(): JsonResponse
    {
        $units = $this->unitService->list();
        
        return response()->json([
            'units' => $units,
            'total' => $units->count(),
            'has_locked_units' => $units->contains('is_locked', true)
        ]);
    }

    /**
     * Create a new unit
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'difficulty' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'order' => 'required|integer|min:1',
            'is_locked' => 'boolean',
        ]);

        $unit = $this->unitService->create($validated);

        return response()->json([
            'message' => 'Unit created successfully',
            'unit' => $this->unitService->attachStats($unit)
        ], 201);
    }

    /**
     * Update an existing unit
     */
    public function update(Unit $unit, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'difficulty' => ['sometimes', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'order' => 'sometimes|integer|min:1',
            'is_locked' => 'sometimes|boolean',
        ]);

        $unit = $this->unitService->update($unit, $validated);

        return response()->json([
            'message' => 'Unit updated successfully',
            'unit' => $this->unitService->attachStats($unit)
        ]);
    }

    /**
     * Delete a unit
     */
    public function destroy(Unit $unit): JsonResponse
    {
        // Check if unit has any completed lessons
        if ($unit->hasCompletedLessons()) {
            return response()->json([
                'message' => 'Cannot delete unit with completed lessons',
                'completed_lessons_count' => $unit->completedLessonsCount()
            ], 422);
        }

        $this->unitService->delete($unit);

        return response()->json([
            'message' => 'Unit deleted successfully'
        ]);
    }

    /**
     * Get unit statistics
     */
    public function stats(Unit $unit): JsonResponse
    {
        return response()->json([
            'unit_id' => $unit->id,
            'stats' => $this->unitService->attachStats($unit)
        ]);
    }

    /**
     * Bulk update unit order
     */
    public function updateOrder(Request $request): JsonResponse
    {
        $request->validate([
            'units' => 'required|array',
            'units.*.id' => 'required|exists:units,id',
            'units.*.order' => 'required|integer|min:1'
        ]);

        foreach ($request->units as $unitData) {
            $unit = Unit::find($unitData['id']);
            if ($unit && $unit->order !== $unitData['order']) {
                $this->unitService->update($unit, ['order' => $unitData['order']]);
            }
        }

        return response()->json([
            'message' => 'Unit order updated successfully',
            'units' => $this->unitService->list()
        ]);
    }

    /**
     * Toggle unit lock status
     */
    public function toggleLock(Unit $unit): JsonResponse
    {
        $unit = $this->unitService->update($unit, [
            'is_locked' => !$unit->is_locked
        ]);

        return response()->json([
            'message' => 'Unit lock status toggled successfully',
            'unit' => $this->unitService->attachStats($unit)
        ]);
    }
}