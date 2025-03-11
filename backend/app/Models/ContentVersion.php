<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'version_number',
        'content',
        'changes',
        'change_type',
        'change_reason',
        'is_major_version',
        'published_at',
        'metadata'
    ];

    protected $casts = [
        'content' => 'array',
        'changes' => 'array',
        'is_major_version' => 'boolean',
        'published_at' => 'datetime',
        'metadata' => 'array'
    ];

    /**
     * The model that owns the version.
     */
    public function versionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who created this version.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create a new version for a model.
     */
    public static function createVersion(
        Model $model,
        array $content,
        array $changes,
        string $changeType,
        array $options = []
    ): self {
        $lastVersion = static::where('versionable_type', get_class($model))
            ->where('versionable_id', $model->id)
            ->latest('version_number')
            ->first();

        $versionNumber = $lastVersion ? $lastVersion->version_number + 1 : 1;

        return static::create([
            'versionable_type' => get_class($model),
            'versionable_id' => $model->id,
            'user_id' => auth()->id(),
            'version_number' => $versionNumber,
            'content' => $content,
            'changes' => $changes,
            'change_type' => $changeType,
            'change_reason' => $options['reason'] ?? null,
            'is_major_version' => $options['is_major'] ?? false,
            'published_at' => $options['published_at'] ?? null,
            'metadata' => $options['metadata'] ?? null,
        ]);
    }

    /**
     * Get the differences between this version and another.
     */
    public function getDiff(self $otherVersion): array
    {
        return array_diff_assoc($this->content, $otherVersion->content);
    }

    /**
     * Restore this version to the versionable model.
     */
    public function restore(): bool
    {
        if (!$this->versionable) {
            return false;
        }

        // Create a new version to record the restore action
        static::createVersion(
            $this->versionable,
            $this->content,
            [],
            'restore',
            [
                'reason' => "Restored from version {$this->version_number}",
                'metadata' => [
                    'restored_from' => $this->version_number,
                    'restored_at' => now()
                ]
            ]
        );

        // Update the model with the restored content
        return $this->versionable->update($this->content);
    }

    /**
     * Get a specific version of a model.
     */
    public static function getVersion(Model $model, int $version): ?self
    {
        return static::where('versionable_type', get_class($model))
            ->where('versionable_id', $model->id)
            ->where('version_number', $version)
            ->first();
    }

    /**
     * Get all versions between two version numbers.
     */
    public static function getVersionsBetween(Model $model, int $from, int $to): array
    {
        return static::where('versionable_type', get_class($model))
            ->where('versionable_id', $model->id)
            ->whereBetween('version_number', [$from, $to])
            ->orderBy('version_number')
            ->get()
            ->toArray();
    }

    /**
     * Check if this is the latest version.
     */
    public function isLatest(): bool
    {
        return !static::where('versionable_type', $this->versionable_type)
            ->where('versionable_id', $this->versionable_id)
            ->where('version_number', '>', $this->version_number)
            ->exists();
    }

    /**
     * Get the version label (e.g., "v1.0").
     */
    public function getVersionLabel(): string
    {
        return 'v' . $this->version_number . 
            ($this->is_major_version ? '.0' : '');
    }

    /**
     * Scope a query to only published versions.
     */
    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at');
    }

    /**
     * Scope a query to only major versions.
     */
    public function scopeMajor($query)
    {
        return $query->where('is_major_version', true);
    }
}