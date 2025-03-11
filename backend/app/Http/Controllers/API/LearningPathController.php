<?php

namespace App\Http\Controllers\API;

use App\Models\LearningPath;
use App\Http\Requests\API\LearningPath\StoreLearningPathRequest;
use App\Http\Requests\API\LearningPath\UpdateLearningPathRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LearningPathController extends BaseAPIController
{
    /**
     * Display a listing of learning paths.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LearningPath::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('target_level')) {
            $query->where('target_level', $request->target_level);
        }

        // Include relationships if requested
        if ($request->has('with_units')) {
            $query->with(['units' => function ($query) {
                $query->orderBy('order');
            }]);
        }

        $perPage = $request->input('per_page', 15);
        $learningPaths = $query->paginate($perPage);

        return $this->sendPaginatedResponse($learningPaths);
    }

    /**
     * Store a newly created learning path.
     */
    public function store(StoreLearningPathRequest $request): JsonResponse
    {
        $learningPath = LearningPath::create($request->validated());

        return $this->sendCreatedResponse($learningPath, 'Learning path created successfully.');
    }

    /**
     * Display the specified learning path.
     */
    public function show(Request $request, LearningPath $learningPath): JsonResponse
    {
        // Load relationships if requested
        if ($request->has('with_units')) {
            $learningPath->load(['units' => function ($query) {
                $query->orderBy('order')->with(['lessons' => function ($query) {
                    $query->orderBy('order');
                }]);
            }]);
        }

        if ($request->has('with_progress') && $request->user()) {
            $learningPath->load(['progress' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        }

        return $this->sendResponse($learningPath);
    }

    /**
     * Update the specified learning path.
     */
    public function update(UpdateLearningPathRequest $request, LearningPath $learningPath): JsonResponse
    {
        $learningPath->update($request->validated());

        return $this->sendResponse($learningPath, 'Learning path updated successfully.');
    }

    /**
     * Remove the specified learning path.
     */
    public function destroy(LearningPath $learningPath): JsonResponse
    {
        if ($learningPath->status === 'published') {
            return $this->sendError('Cannot delete a published learning path.');
        }

        $learningPath->delete();

        return $this->sendNoContentResponse();
    }

    /**
     * Get learning paths by target level.
     */
    public function byLevel(string $level): JsonResponse
    {
        $learningPaths = LearningPath::where('target_level', $level)
            ->where('status', 'published')
            ->with(['units' => function ($query) {
                $query->orderBy('order');
            }])
            ->get();

        return $this->sendResponse($learningPaths);
    }

    /**
     * Get user progress for a learning path.
     */
    public function progress(Request $request, LearningPath $learningPath): JsonResponse
    {
        $progress = $learningPath->progress()
            ->where('user_id', $request->user()->id)
            ->first();

        $unitsProgress = $learningPath->units()
            ->with(['progress' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->get()
            ->map(function ($unit) {
                $progress = $unit->progress->first();
                return [
                    'unit_id' => $unit->id,
                    'status' => $progress ? $progress->status : 'not_started',
                    'completion_percentage' => $unit->getCompletionPercentage($unit->progress->first()?->user_id ?? 0)
                ];
            });

        return $this->sendResponse([
            'learning_path_progress' => $progress ? $progress->status : 'not_started',
            'units_progress' => $unitsProgress
        ]);
    }

    /**
     * Update the status of a learning path.
     */
    public function updateStatus(Request $request, LearningPath $learningPath): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:draft,published,archived']
        ]);

        $learningPath->status = $request->status;
        $learningPath->save();

        return $this->sendResponse($learningPath, 'Learning path status updated successfully.');
    }
}