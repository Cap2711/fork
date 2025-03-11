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

class Lesson extends Model
{
    use HasFactory, HasVersions, HasAuditLog, HasMedia;

    const AUDIT_AREA = 'lessons';

    protected $fillable = [
        'unit_id',
        'title',
        'description',
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
        'description',
        'order'
    ];

    /**
     * Get the unit that owns the lesson.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the learning path through the unit.
     */
    public function learningPath()
    {
        return $this->unit->learningPath();
    }

    /**
     * Get the sections for the lesson.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('order');
    }

    /**
     * Get the vocabulary items for the lesson.
     */
    public function vocabularyItems(): HasMany
    {
        return $this->hasMany(VocabularyItem::class);
    }

    /**
     * Get all progress records for this lesson.
     */
    public function progress(): MorphMany
    {
        return $this->morphMany(UserProgress::class, 'trackable');
    }

    /**
     * Check if all sections are completed for a user
     */
    public function isCompletedByUser(int $userId): bool
    {
        $totalSections = $this->sections()->count();
        if ($totalSections === 0) {
            return false;
        }

        $completedSections = $this->sections()
            ->whereHas('progress', function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->where('status', 'completed');
            })
            ->count();

        return $completedSections === $totalSections;
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
            'order' => $this->order,
            'sections_count' => $this->sections()->count(),
            'vocabulary_count' => $this->vocabularyItems()->count(),
            'thumbnail' => $this->getMedia('thumbnail')->first()?->getUrl(),
            'unit' => [
                'id' => $this->unit->id,
                'title' => $this->unit->title,
                'learning_path' => [
                    'id' => $this->unit->learningPath->id,
                    'title' => $this->unit->learningPath->title
                ]
            ],
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
            'title' => $this->title,
            'description' => $this->description,
            'order' => $this->order,
            'sections' => $this->sections->map->getExportData()->toArray(),
            'vocabulary_items' => $this->vocabularyItems->map->getExportData()->toArray(),
            'media' => $this->media->groupBy('collection_name')->toArray(),
        ];
    }

    /**
     * Import data from an export structure
     */
    public static function importData(array $data, Unit $unit): self
    {
        $lesson = static::create([
            'unit_id' => $unit->id,
            'title' => $data['title'],
            'description' => $data['description'],
            'order' => $data['order']
        ]);

        foreach ($data['sections'] ?? [] as $sectionData) {
            Section::importData($sectionData, $lesson);
        }

        foreach ($data['vocabulary_items'] ?? [] as $itemData) {
            VocabularyItem::create([
                'lesson_id' => $lesson->id,
                'word' => $itemData['word'],
                'translation' => $itemData['translation'],
                'example' => $itemData['example'] ?? null
            ]);
        }

        return $lesson;
    }

    /**
     * Get the next lesson in the unit
     */
    public function getNextLesson(): ?self
    {
        return static::where('unit_id', $this->unit_id)
            ->where('order', '>', $this->order)
            ->orderBy('order')
            ->first();
    }

    /**
     * Get the previous lesson in the unit
     */
    public function getPreviousLesson(): ?self
    {
        return static::where('unit_id', $this->unit_id)
            ->where('order', '<', $this->order)
            ->orderBy('order', 'desc')
            ->first();
    }

    /**
     * Get all media collections available for lessons
     */
    public static function getMediaCollections(): array
    {
        return [
            'thumbnail' => [
                'max_files' => 1,
                'conversions' => [
                    'thumb' => ['width' => 100, 'height' => 100],
                    'preview' => ['width' => 300, 'height' => 300]
                ]
            ],
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
            ]
        ];
    }
}