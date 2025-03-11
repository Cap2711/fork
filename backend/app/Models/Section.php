<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasMedia;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Section extends Model
{
    use HasFactory, HasVersions, HasAuditLog, HasMedia;

    const AUDIT_AREA = 'sections';

    protected $fillable = [
        'lesson_id',
        'title',
        'content',
        'order'
    ];

    protected $casts = [
        'order' => 'integer'
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'title',
        'content',
        'order'
    ];

    /**
     * Get the lesson that owns the section.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * Get the exercises for the section.
     */
    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('order');
    }

    /**
     * Get all progress records for this section.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Check if all exercises are completed for a user
     */
    public function isCompletedByUser(int $userId): bool
    {
        $totalExercises = $this->exercises()->count();
        if ($totalExercises === 0) {
            return $this->progress()
                ->where('user_id', $userId)
                ->where('status', 'completed')
                ->exists();
        }

        $completedExercises = $this->exercises()
            ->whereHas('progress', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', 'completed');
            })
            ->count();

        return $completedExercises === $totalExercises;
    }

    /**
     * Get content preview data
     */
    public function getPreviewData(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content_preview' => str_limit(strip_tags($this->content), 200),
            'order' => $this->order,
            'exercises_count' => $this->exercises()->count(),
            'media_count' => $this->getMediaCounts(),
            'lesson' => [
                'id' => $this->lesson->id,
                'title' => $this->lesson->title,
                'unit' => [
                    'id' => $this->lesson->unit->id,
                    'title' => $this->lesson->unit->title
                ]
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Get media counts by collection
     */
    protected function getMediaCounts(): array
    {
        return $this->media
            ->groupBy('collection_name')
            ->map(function ($items) {
                return $items->count();
            })
            ->toArray();
    }

    /**
     * Get the export data structure
     */
    public function getExportData(): array
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'order' => $this->order,
            'exercises' => $this->exercises->map->getExportData()->toArray(),
            'media' => $this->media->groupBy('collection_name')->toArray(),
        ];
    }

    /**
     * Import data from an export structure
     */
    public static function importData(array $data, Lesson $lesson): self
    {
        $section = static::create([
            'lesson_id' => $lesson->id,
            'title' => $data['title'],
            'content' => $data['content'],
            'order' => $data['order']
        ]);

        foreach ($data['exercises'] ?? [] as $exerciseData) {
            Exercise::importData($exerciseData, $section);
        }

        return $section;
    }

    /**
     * Get all media collections available for sections
     */
    public static function getMediaCollections(): array
    {
        return [
            'content_images' => [
                'max_files' => 10,
                'conversions' => [
                    'thumb' => ['width' => 100, 'height' => 100],
                    'content' => ['width' => 800, 'height' => null]
                ]
            ],
            'audio' => [
                'max_files' => 5,
                'allowed_types' => ['audio/mpeg', 'audio/wav']
            ],
            'video' => [
                'max_files' => 3,
                'allowed_types' => ['video/mp4', 'video/webm'],
                'max_size' => 50 * 1024 * 1024 // 50MB
            ],
            'attachments' => [
                'max_files' => 5,
                'allowed_types' => [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ]
            ]
        ];
    }

    /**
     * Get the next section in the lesson
     */
    public function getNextSection(): ?self
    {
        return static::where('lesson_id', $this->lesson_id)
            ->where('order', '>', $this->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * Get the previous section in the lesson
     */
    public function getPreviousSection(): ?self
    {
        return static::where('lesson_id', $this->lesson_id)
            ->where('order', '<', $this->order)
            ->orderBy('order', 'desc')
            ->first();
    }
}