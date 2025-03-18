<?php

namespace App\Models\Traits;

use App\Models\ContentVersion;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;

trait HasVersions
{
    protected array $oldAttributes = [];

    public function versions(): MorphMany
    {
        return $this->morphMany(ContentVersion::class, 'versionable');
    }

    protected static function bootHasVersions()
    {
        static::creating(function ($model) {
            $model->oldAttributes = [];
        });

        static::created(function ($model) {
            $model->recordVersion('create');
        });

        static::updating(function ($model) {
            $model->oldAttributes = $model->getOriginal();
        });

        static::updated(function ($model) {
            if ($model->hasVersionChanges()) {
                $model->recordVersion('update');
            }
        });

        static::deleting(function ($model) {
            $model->oldAttributes = $model->getAttributes();
        });

        static::deleted(function ($model) {
            $model->recordVersion('delete');
        });
    }

    public function hasVersionChanges(): bool
    {
        $changes = array_diff_assoc($this->getAttributes(), $this->oldAttributes);
        unset($changes['updated_at']); // Ignore timestamp changes
        return !empty($changes);
    }

    public function getVersionChanges(): array
    {
        $changes = [];
        $current = $this->getAttributes();

        foreach ($current as $key => $value) {
            if (!array_key_exists($key, $this->oldAttributes)) {
                $changes[$key] = [
                    'old' => null,
                    'new' => $value
                ];
            } elseif ($this->oldAttributes[$key] !== $value) {
                $changes[$key] = [
                    'old' => $this->oldAttributes[$key],
                    'new' => $value
                ];
            }
        }

        foreach ($this->oldAttributes as $key => $value) {
            if (!array_key_exists($key, $current)) {
                $changes[$key] = [
                    'old' => $value,
                    'new' => null
                ];
            }
        }

        return $changes;
    }

    public function recordVersion(string $changeType): ContentVersion
    {
        $lastVersion = $this->versions()
            ->orderBy('version_number', 'desc')
            ->first();

        $versionNumber = $lastVersion ? $lastVersion->version_number + 1 : 1;

        // Get the changes based on the operation type
        $changes = $changeType === 'create' 
            ? array_combine(
                array_keys($this->getAttributes()),
                array_map(function ($value) {
                    return ['old' => null, 'new' => $value];
                }, $this->getAttributes())
            )
            : ($changeType === 'delete' 
                ? array_combine(
                    array_keys($this->oldAttributes),
                    array_map(function ($value) {
                        return ['old' => $value, 'new' => null];
                    }, $this->oldAttributes)
                )
                : $this->getVersionChanges()
            );

        return $this->versions()->create([
            'user_id' => Auth::id() ?? 1, // Use system user (1) if no auth user
            'version_number' => $versionNumber,
            'content' => $changeType === 'delete' ? $this->oldAttributes : $this->getAttributes(),
            'changes' => $changes,
            'change_type' => $changeType,
            'is_major_version' => false,
        ]);
    }
}