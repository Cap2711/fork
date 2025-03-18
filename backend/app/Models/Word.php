<?php

namespace App\Models;

use App\Models\Traits\{HasVersions, HasAuditLog};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, BelongsToMany};
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Word extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasAuditLog, HasVersions;

    protected $fillable = [
        'language_id',
        'text',
        'pronunciation_key',
        'part_of_speech',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    protected array $auditLogEvents = [
        'created' => 'Created new word: :text (:language)',
        'updated' => 'Updated word: :text',
        'deleted' => 'Deleted word: :text',
    ];

    protected array $auditLogProperties = [
        'text',
        'pronunciation_key',
        'part_of_speech',
        'metadata'
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pronunciation')
            ->singleFile()
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/wav']);
    }

    /**
     * Get the language this word belongs to.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get the translations of this word.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(WordTranslation::class);
    }

    /**
     * Get the sentences this word appears in.
     */
    public function sentences(): BelongsToMany
    {
        return $this->belongsToMany(Sentence::class, 'sentence_words')
            ->withPivot(['position', 'start_time', 'end_time', 'metadata'])
            ->orderBy('position');
    }

    /**
     * Get this word's usage examples.
     */
    public function usageExamples(): HasMany
    {
        return $this->hasMany(UsageExample::class);
    }

    /**
     * Custom attribute for audit log message.
     */
    public function getLanguageAttribute(): string
    {
        return $this->language?->code ?? 'unknown';
    }
}