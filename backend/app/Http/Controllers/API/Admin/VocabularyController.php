<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\Vocabulary;
use App\Http\Requests\API\Vocabulary\StoreVocabularyRequest;
use App\Http\Requests\API\Vocabulary\UpdateVocabularyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VocabularyController extends BaseAPIController
{
    /**
     * Display a listing of all vocabulary items.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vocabulary::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('word', 'like', "%{$search}%")
                    ->orWhere('definition', 'like', "%{$search}%")
                    ->orWhere('example', 'like', "%{$search}%");
            });
        }

        // Include relationships if requested
        if ($request->has('with_lessons')) {
            $query->with('lessons');
        }

        $perPage = $request->input('per_page', 15);
        $vocabulary = $query->paginate($perPage);

        return $this->sendPaginatedResponse($vocabulary);
    }

    /**
     * Store a newly created vocabulary item.
     */
    public function store(StoreVocabularyRequest $request): JsonResponse
    {
        $vocabulary = Vocabulary::create($request->validated());

        // Log the creation for audit trail
        activity()
            ->performedOn($vocabulary)
            ->causedBy($request->user())
            ->withProperties(['data' => $request->validated()])
            ->log('created');

        return $this->sendCreatedResponse($vocabulary, 'Vocabulary item created successfully.');
    }

    /**
     * Display the specified vocabulary item.
     */
    public function show(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        // Load relationships if requested
        if ($request->has('with_lessons')) {
            $vocabulary->load('lessons');
        }

        if ($request->has('with_versions')) {
            // Load version history if requested
            $vocabulary->load('versions');
        }

        return $this->sendResponse($vocabulary);
    }

    /**
     * Update the specified vocabulary item.
     */
    public function update(UpdateVocabularyRequest $request, Vocabulary $vocabulary): JsonResponse
    {
        $oldData = $vocabulary->toArray();
        $vocabulary->update($request->validated());

        // Log the update for audit trail
        activity()
            ->performedOn($vocabulary)
            ->causedBy($request->user())
            ->withProperties([
                'old' => $oldData,
                'new' => $request->validated()
            ])
            ->log('updated');

        return $this->sendResponse($vocabulary, 'Vocabulary item updated successfully.');
    }

    /**
     * Remove the specified vocabulary item.
     */
    public function destroy(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        // Prevent deletion of published vocabulary items
        if ($vocabulary->status === 'published') {
            return $this->sendError('Cannot delete a published vocabulary item. Archive it first.', 422);
        }

        $data = $vocabulary->toArray();
        $vocabulary->delete();

        // Log the deletion for audit trail
        activity()
            ->performedOn($vocabulary)
            ->causedBy($request->user())
            ->withProperties(['data' => $data])
            ->log('deleted');

        return $this->sendNoContentResponse();
    }

    /**
     * Update the status of a vocabulary item.
     */
    public function updateStatus(Request $request, Vocabulary $vocabulary): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:draft,published,archived']
        ]);

        $oldStatus = $vocabulary->status;
        $vocabulary->status = $request->status;
        $vocabulary->save();

        // Log the status change for audit trail
        activity()
            ->performedOn($vocabulary)
            ->causedBy($request->user())
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $request->status
            ])
            ->log('status_updated');

        return $this->sendResponse($vocabulary, 'Vocabulary item status updated successfully.');
    }

    /**
     * Bulk import vocabulary items.
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.word' => ['required', 'string', 'max:255'],
            'items.*.definition' => ['required', 'string'],
            'items.*.example' => ['nullable', 'string'],
            'items.*.level' => ['nullable', 'string', 'in:beginner,intermediate,advanced'],
            'items.*.category' => ['nullable', 'string', 'max:255'],
            'items.*.status' => ['nullable', 'string', 'in:draft,published,archived'],
        ]);

        $items = $request->items;
        $importedItems = [];

        foreach ($items as $item) {
            $vocabulary = Vocabulary::create($item);
            $importedItems[] = $vocabulary;

            // Log the creation for audit trail
            activity()
                ->performedOn($vocabulary)
                ->causedBy($request->user())
                ->withProperties(['data' => $item])
                ->log('bulk_imported');
        }

        return $this->sendResponse($importedItems, count($importedItems) . ' vocabulary items imported successfully.');
    }

    /**
     * Export vocabulary items.
     */
    public function export(Request $request): JsonResponse
    {
        $query = Vocabulary::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $vocabulary = $query->get(['id', 'word', 'definition', 'example', 'level', 'category', 'status']);

        return $this->sendResponse($vocabulary, 'Vocabulary items exported successfully.');
    }
}
