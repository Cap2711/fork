<?php

namespace App\Models;

use App\Models\Traits\{HasVersions, HasAuditLog};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, BelongsToMany};
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Sentence extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasAuditLog, HasVersions;

    protected $fillable = [
        'language_id',
        'text',
        'pronunciation_key',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    protected array $auditLogEvents = [
        'created' => 'Created new sentence in :language: :text',
        'updated' => 'Updated sentence: :text',
        'deleted' => 'Deleted sentence: :text'
    ];

    protected array $auditLogProperties = [
        'text',
        'pronunciation_key',
        'metadata'
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('audio')
            ->singleFile()
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/wav']);

        $this->addMediaCollection('audio_slow')
            ->singleFile()
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/wav']);
    }

    /**
     * Get the language this sentence belongs to.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get the translations of this sentence.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(SentenceTranslation::class);
    }

    /**
     * Get the words in this sentence with their position and timing information.
     */
    public function words(): BelongsToMany
    {
        return $this->belongsToMany(Word::class, 'sentence_words')
            ->withPivot(['position', 'start_time', 'end_time', 'metadata'])
            ->orderBy('position');
    }

    /**
     * Get the usage examples that use this sentence.
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