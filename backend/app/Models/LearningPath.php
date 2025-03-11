<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasMedia;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LearningPath extends Model
{
    use HasFactory, HasVersions, HasAuditLog, HasMedia;

    const AUDIT_AREA = 'learning_paths';

    protected $fillable = [
        'title',
        'description',
        'target_level',
        'status',
        'review_status'
    ];

    protected $casts = [
        'status' => 'string',
        'review_status' => 'string'
    ];

    /**
     * The attributes that should be version controlled.
     */
    protected array $versionedAttributes = [
        'title',
        'description',
        'target_level',
        'status',
        'review_status'
    ];

    /**
     * Get the units for the learning path.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class)->orderBy('order');
    }

    /**
     * Get all progress records for this learning path.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Get all reviews for this learning path.
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable', 'content_type', 'content_id');
    }

    /**
     * Get the published scope.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Check if the learning path is published.
     */
    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /**
     * Get the total number of lessons in this learning path.
     */
    public function getLessonCount(): int
    {
        return $this->units()->withCount('lessons')->get()->sum('lessons_count');
    }

    /**
     * Get the completion percentage for a specific user
     */
    public function getCompletionPercentage(int $userId): float
    {
        $units = $this->units()->with(['progress' => function ($query) use ($userId) {
            $query->where('user_id', $userId);
        }])->get();

        if ($units->isEmpty()) {
            return 0;
        }

        $completedUnits = $units->filter(function ($unit) {
            return $unit->progress->contains('status', 'completed');
        })->count();

        return ($completedUnits / $units->count()) * 100;
    }

    /**
     * Get content preview data
     */
    public function getPreviewData(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'target_level' => $this->target_level,
            'status' => $this->status,
            'units_count' => $this->units()->count(),
            'lessons_count' => $this->getLessonCount(),
            'thumbnail' => collect($this->getMedia('thumbnail'))->first()?->getUrl(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Get the export data structure
     */
    public function getExportData(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'target_level' => $this->target_level,
            'status' => $this->status,
            'units' => $this->units->map->getExportData()->toArray(),
            'media' => $this->media->groupBy('collection_name')->toArray(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Import data from an export structure
     */
    public static function importData(array $data): self
    {
        $learningPath = static::create([
            'title' => $data['title'],
            'description' => $data['description'],
            'target_level' => $data['target_level'],
            'status' => 'draft'
        ]);

        foreach ($data['units'] ?? [] as $unitData) {
            Unit::importData($unitData, $learningPath);
        }

        return $learningPath;
    }
}