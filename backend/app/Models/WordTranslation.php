<?php

namespace App\Models;

use App\Models\Traits\{HasVersions, HasAuditLog};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class WordTranslation extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, HasAuditLog, HasVersions;

    protected $fillable = [
        'word_id',
        'language_id',
        'text',
        'pronunciation_key',
        'context_notes',
        'usage_examples',
        'translation_order'
    ];

    protected $casts = [
        'usage_examples' => 'array',
        'translation_order' => 'integer'
    ];

    protected array $auditLogEvents = [
        'created' => 'Created translation for :word in :language: :text',
        'updated' => 'Updated translation for :word in :language',
        'deleted' => 'Deleted translation for :word in :language'
    ];

    protected array $auditLogProperties = [
        'text',
        'pronunciation_key',
        'context_notes',
        'usage_examples',
        'translation_order'
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pronunciation')
            ->singleFile()
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/wav']);
    }

    /**
     * Get the original word this translation belongs to.
     */
    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }

    /**
     * Get the target language of this translation.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Custom attributes for audit log message.
     */
    public function getWordAttribute(): string
    {
        return $this->word?->text ?? 'unknown';
    }

    public function getLanguageAttribute(): string
    {
        return $this->language?->code ?? 'unknown';
    }
}