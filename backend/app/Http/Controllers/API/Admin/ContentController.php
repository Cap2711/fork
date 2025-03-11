<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentController extends BaseAPIController
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
        'quiz-questions' => 'App\\Models\\QuizQuestion',
        'vocabulary' => 'App\\Models\\Vocabulary',
        'guide-entries' => 'App\\Models\\GuideBookEntry',
    ];

    /**
     * Publish a content item
     */
    public function publish(Request $request, string $type, int $id): JsonResponse
    {
        if (!array_key_exists($type, $this->contentTypeMap)) {
            return $this->sendError('Invalid content type.', 400);
        }

        $modelClass = $this->contentTypeMap[$type];
        $content = $modelClass::findOrFail($id);

        // Check if the content is already published
        if ($content->status === 'published') {
            return $this->sendError('Content is already published.', 400);
        }

        // Create a version snapshot before publishing
        $this->createVersionSnapshot($content, 'before_publish');

        // Update the status to published
        $content->status = 'published';
        $content->published_at = now();
        $content->save();

        // Log the publishing action
        activity()
            ->performedOn($content)
            ->causedBy($request->user())
            ->log('published');

        return $this->sendResponse($content, 'Content published successfully.');
    }

    /**
     * Unpublish a content item
     */
    public function unpublish(Request $request, string $type, int $id): JsonResponse
    {
        if (!array_key_exists($type, $this->contentTypeMap)) {
            return $this->sendError('Invalid content type.', 400);
        }

        $modelClass = $this->contentTypeMap[$type];
        $content = $modelClass::findOrFail($id);

        // Check if the content is already unpublished
        if ($content->status !== 'published') {
            return $this->sendError('Content is not currently published.', 400);
        }

        // Create a version snapshot before unpublishing
        $this->createVersionSnapshot($content, 'before_unpublish');

        // Update the status to draft
        $content->status = 'draft';
        $content->save();

        // Log the unpublishing action
        activity()
            ->performedOn($content)
            ->causedBy($request->user())
            ->log('unpublished');

        return $this->sendResponse($content, 'Content unpublished successfully.');
    }

    /**
     * Get versions for a content item
     */
    public function versions(string $type, int $id): JsonResponse
    {
        if (!array_key_exists($type, $this->contentTypeMap)) {
            return $this->sendError('Invalid content type.', 400);
        }

        $modelClass = $this->contentTypeMap[$type];
        $content = $modelClass::findOrFail($id);

        // Get all versions for this content
        $versions = DB::table('content_versions')
            ->where('content_type', get_class($content))
            ->where('content_id', $content->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendResponse($versions);
    }

    /**
     * Restore a content item to a previous version
     */
    public function restore(Request $request, string $type, int $id, int $version): JsonResponse
    {
        if (!array_key_exists($type, $this->contentTypeMap)) {
            return $this->sendError('Invalid content type.', 400);
        }

        $modelClass = $this->contentTypeMap[$type];
        $content = $modelClass::findOrFail($id);

        // Find the version
        $versionRecord = DB::table('content_versions')
            ->where('content_type', get_class($content))
            ->where('content_id', $content->id)
            ->where('id', $version)
            ->first();

        if (!$versionRecord) {
            return $this->sendError('Version not found.', 404);
        }

        // Create a snapshot of the current state before restoring
        $this->createVersionSnapshot($content, 'before_restore');

        // Restore the content to the version data
        $versionData = json_decode($versionRecord->content_data, true);
        $content->fill($versionData);
        $content->save();

        // Log the restore action
        activity()
            ->performedOn($content)
            ->causedBy($request->user())
            ->withProperties([
                'version_id' => $version,
                'version_label' => $versionRecord->label
            ])
            ->log('restored_version');

        return $this->sendResponse($content, 'Content restored to version successfully.');
    }

    /**
     * Create a version snapshot of the content
     */
    protected function createVersionSnapshot($content, string $label): void
    {
        // Store a snapshot of the current content state
        DB::table('content_versions')->insert([
            'content_type' => get_class($content),
            'content_id' => $content->id,
            'content_data' => json_encode($content->toArray()),
            'label' => $label,
            'created_at' => now(),
            'created_by' => auth()->id()
        ]);
    }

    /**
     * Bulk update content status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.type' => 'required|string',
            'items.*.id' => 'required|integer',
            'status' => 'required|string|in:draft,published,archived'
        ]);

        $items = $request->items;
        $status = $request->status;
        $results = [];

        foreach ($items as $item) {
            if (!array_key_exists($item['type'], $this->contentTypeMap)) {
                $results[] = [
                    'type' => $item['type'],
                    'id' => $item['id'],
                    'success' => false,
                    'message' => 'Invalid content type'
                ];
                continue;
            }

            $modelClass = $this->contentTypeMap[$item['type']];
            
            try {
                $content = $modelClass::findOrFail($item['id']);
                $oldStatus = $content->status;
                $content->status = $status;
                $content->save();

                // Log the status change
                activity()
                    ->performedOn($content)
                    ->causedBy($request->user())
                    ->withProperties([
                        'old_status' => $oldStatus,
                        'new_status' => $status
                    ])
                    ->log('status_updated');

                $results[] = [
                    'type' => $item['type'],
                    'id' => $item['id'],
                    'success' => true,
                    'message' => 'Status updated successfully'
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'type' => $item['type'],
                    'id' => $item['id'],
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return $this->sendResponse($results, 'Bulk status update completed.');
    }

    /**
     * Search across all content types
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'types' => 'nullable|array',
            'types.*' => 'string',
            'status' => 'nullable|string|in:draft,published,archived',
        ]);

        $query = $request->query;
        $types = $request->types ?? array_keys($this->contentTypeMap);
        $status = $request->status;
        
        $results = [];

        foreach ($types as $type) {
            if (!array_key_exists($type, $this->contentTypeMap)) {
                continue;
            }

            $modelClass = $this->contentTypeMap[$type];
            $searchQuery = $modelClass::query();
            
            // Apply status filter if provided
            if ($status) {
                $searchQuery->where('status', $status);
            }
            
            // Apply search based on common fields
            // This assumes all content types have title/name fields
            $searchQuery->where(function ($q) use ($query) {
                // Try common field names that might exist across models
                if (Schema::hasColumn((new $modelClass)->getTable(), 'title')) {
                    $q->orWhere('title', 'like', "%{$query}%");
                }
                
                if (Schema::hasColumn((new $modelClass)->getTable(), 'name')) {
                    $q->orWhere('name', 'like', "%{$query}%");
                }
                
                if (Schema::hasColumn((new $modelClass)->getTable(), 'description')) {
                    $q->orWhere('description', 'like', "%{$query}%");
                }
                
                if (Schema::hasColumn((new $modelClass)->getTable(), 'content')) {
                    $q->orWhere('content', 'like', "%{$query}%");
                }
            });
            
            $typeResults = $searchQuery->limit(10)->get();
            
            if ($typeResults->isNotEmpty()) {
                $results[$type] = $typeResults;
            }
        }

        return $this->sendResponse($results);
    }
}
