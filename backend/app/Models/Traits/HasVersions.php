<?php

namespace App\Models\Traits;

use App\Models\ContentVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasVersions
{
    /**
     * Boot the trait.
     */
    protected static function bootHasVersions()
    {
        static::created(function (Model $model) {
            $model->createVersion('create');
        });

        static::updated(function (Model $model) {
            $model->createVersion('update');
        });

        static::deleted(function (Model $model) {
            $model->createVersion('delete');
        });
    }

    /**
     * Get all versions of this model.
     */
    public function versions(): MorphMany
    {
        return $this->morphMany(ContentVersion::class, 'versionable')
            ->orderByDesc('version_number');
    }

    /**
     * Create a new version of this model.
     */
    public function createVersion(
        string $changeType,
        array $options = []
    ): ContentVersion {
        $oldValues = $this->getOriginal();
        $newValues = $this->getAttributes();

        // Filter out unchanged values
        $changes = array_diff_assoc($newValues, $oldValues);

        return ContentVersion::createVersion(
            $this,
            $newValues,
            $changes,
            $changeType,
            $options
        );
    }

    /**
     * Get a specific version of this model.
     */
    public function getVersion(int $versionNumber): ?ContentVersion
    {
        return $this->versions()
            ->where('version_number', $versionNumber)
            ->first();
    }

    /**
     * Restore to a specific version.
     */
    public function restoreVersion(int $versionNumber): bool
    {
        $version = $this->getVersion($versionNumber);
        if (!$version) {
            return false;
        }

        return $version->restore();
    }

    /**
     * Get the latest version.
     */
    public function getLatestVersion(): ?ContentVersion
    {
        return $this->versions()->first();
    }

    /**
     * Get the latest published version.
     */
    public function getLatestPublishedVersion(): ?ContentVersion
    {
        return $this->versions()
            ->whereNotNull('published_at')
            ->first();
    }

    /**
     * Compare two versions.
     */
    public function compareVersions(int $version1, int $version2): array
    {
        $v1 = $this->getVersion($version1);
        $v2 = $this->getVersion($version2);

        if (!$v1 || !$v2) {
            return [];
        }

        return $v1->getDiff($v2);
    }

    /**
     * Get version history with changes.
     */
    public function getVersionHistory(): array
    {
        return $this->versions()
            ->with('user')
            ->get()
            ->map(function ($version) {
                return [
                    'version' => $version->version_number,
                    'label' => $version->getVersionLabel(),
                    'type' => $version->change_type,
                    'changes' => $version->changes,
                    'user' => $version->user?->name ?? 'System',
                    'created_at' => $version->created_at,
                    'is_published' => !is_null($version->published_at),
                    'is_major' => $version->is_major_version,
                ];
            })
            ->toArray();
    }

    /**
     * Publish the current version.
     */
    public function publish(): bool
    {
        $latestVersion = $this->getLatestVersion();
        if (!$latestVersion) {
            return false;
        }

        $latestVersion->update([
            'published_at' => now()
        ]);

        return true;
    }

    /**
     * Check if the model has any published versions.
     */
    public function hasPublishedVersion(): bool
    {
        return $this->versions()
            ->whereNotNull('published_at')
            ->exists();
    }
}