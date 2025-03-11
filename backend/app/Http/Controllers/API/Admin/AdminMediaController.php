<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\BaseAPIController;
use App\Models\MediaFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class AdminMediaController extends BaseAPIController
{
    /**
     * Upload a single media file.
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:102400', // 100MB max
            'mediable_type' => 'required|string',
            'mediable_id' => 'required|integer',
            'collection_name' => 'required|string',
            'disk' => 'nullable|string|in:local,s3,public',
            'conversions' => 'nullable|array',
            'custom_properties' => 'nullable|array'
        ]);

        try {
            $file = $request->file('file');
            $model = $this->getModelFromType($request->mediable_type, $request->mediable_id);

            if (!$model) {
                return $this->sendError('Invalid mediable type or ID.', 404);
            }

            $mediaFile = $model->addMedia(
                $file,
                $request->collection_name,
                [
                    'disk' => $request->input('disk', config('filesystems.default')),
                    'conversions' => $request->input('conversions', []),
                    'custom_properties' => $request->input('custom_properties', [])
                ]
            );

            return $this->sendCreatedResponse($mediaFile);

        } catch (\Exception $e) {
            return $this->sendError('Failed to upload file: ' . $e->getMessage());
        }
    }

    /**
     * Upload multiple media files.
     */
    public function bulkUpload(Request $request): JsonResponse
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|max:102400', // 100MB max per file
            'mediable_type' => 'required|string',
            'mediable_id' => 'required|integer',
            'collection_name' => 'required|string',
            'disk' => 'nullable|string|in:local,s3,public',
            'conversions' => 'nullable|array',
            'custom_properties' => 'nullable|array'
        ]);

        try {
            $model = $this->getModelFromType($request->mediable_type, $request->mediable_id);

            if (!$model) {
                return $this->sendError('Invalid mediable type or ID.', 404);
            }

            $uploadedFiles = [];
            $failedFiles = [];

            foreach ($request->file('files') as $file) {
                try {
                    $mediaFile = $model->addMedia(
                        $file,
                        $request->collection_name,
                        [
                            'disk' => $request->input('disk', config('filesystems.default')),
                            'conversions' => $request->input('conversions', []),
                            'custom_properties' => $request->input('custom_properties', [])
                        ]
                    );
                    $uploadedFiles[] = $mediaFile;
                } catch (\Exception $e) {
                    $failedFiles[] = [
                        'name' => $file->getClientOriginalName(),
                        'error' => $e->getMessage()
                    ];
                }
            }

            return $this->sendResponse([
                'uploaded' => $uploadedFiles,
                'failed' => $failedFiles
            ]);

        } catch (\Exception $e) {
            return $this->sendError('Bulk upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete a media file.
     */
    public function destroy(MediaFile $media): JsonResponse
    {
        try {
            $media->delete();
            return $this->sendNoContentResponse();
        } catch (\Exception $e) {
            return $this->sendError('Failed to delete file: ' . $e->getMessage());
        }
    }

    /**
     * Optimize an image file.
     */
    public function optimize(MediaFile $media): JsonResponse
    {
        if (!$media->isImage()) {
            return $this->sendError('Only image files can be optimized.', 422);
        }

        try {
            $disk = Storage::disk($media->disk);
            $image = Image::make($disk->get($media->path));

            // Optimize image while maintaining quality
            $image->encode(null, 85); // 85% quality

            // Save optimized version
            $disk->put($media->path, $image->stream());

            // Update file size
            $media->update([
                'size' => $disk->size($media->path)
            ]);

            return $this->sendResponse($media);

        } catch (\Exception $e) {
            return $this->sendError('Failed to optimize image: ' . $e->getMessage());
        }
    }

    /**
     * Generate additional image conversions.
     */
    public function generateConversions(Request $request, MediaFile $media): JsonResponse
    {
        if (!$media->isImage()) {
            return $this->sendError('Only image files can have conversions.', 422);
        }

        $request->validate([
            'conversions' => 'required|array',
            'conversions.*.name' => 'required|string',
            'conversions.*.width' => 'nullable|integer|min:1',
            'conversions.*.height' => 'nullable|integer|min:1',
            'conversions.*.quality' => 'nullable|integer|min:1|max:100',
            'conversions.*.format' => 'nullable|string|in:jpg,png,webp'
        ]);

        try {
            $disk = Storage::disk($media->disk);
            $image = Image::make($disk->get($media->path));
            $conversions = [];

            foreach ($request->conversions as $conversion) {
                $convertedImage = clone $image;

                // Resize if dimensions provided
                if (!empty($conversion['width']) || !empty($conversion['height'])) {
                    $convertedImage->resize(
                        $conversion['width'] ?? null,
                        $conversion['height'] ?? null,
                        function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        }
                    );
                }

                // Generate conversion path
                $format = $conversion['format'] ?? pathinfo($media->file_name, PATHINFO_EXTENSION);
                $conversionName = $conversion['name'];
                $fileName = sprintf(
                    '%s-%s.%s',
                    pathinfo($media->file_name, PATHINFO_FILENAME),
                    $conversionName,
                    $format
                );
                $path = sprintf(
                    '%s/conversions/%s',
                    dirname($media->path),
                    $fileName
                );

                // Save conversion
                $disk->put(
                    $path,
                    $convertedImage->encode($format, $conversion['quality'] ?? 90)
                );

                $conversions[$conversionName] = $path;
            }

            // Update media record with new conversions
            $media->update([
                'conversions' => array_merge(
                    $media->conversions ?? [],
                    $conversions
                )
            ]);

            return $this->sendResponse($media);

        } catch (\Exception $e) {
            return $this->sendError('Failed to generate conversions: ' . $e->getMessage());
        }
    }

    /**
     * Get model instance from type and ID.
     */
    private function getModelFromType(string $type, int $id): ?object
    {
        $modelClass = 'App\\Models\\' . Str::studly(Str::singular($type));
        
        if (!class_exists($modelClass)) {
            return null;
        }

        return $modelClass::find($id);
    }
}