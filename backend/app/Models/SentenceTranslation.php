<?php

namespace App\Models;

use App\Models\Traits\HasMedia;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SentenceTranslation extends Model
{
    use HasFactory, HasMedia, HasAuditLog;

    const AUDIT_AREA = 'sentence_translations';

    protected $fillable = [
        'sentence_id',
        'language_id',
        'text',
        'pronunciation_key',
        'context_notes'
    ];

    /**
     * Get the sentence being translated.
     */
    public function sentence(): BelongsTo
    {
        return $this->belongsTo(Sentence::class);
    }

    /**
     * Get the target language.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        // Translation pronunciation
        $this->addMediaCollection('pronunciation')
            ->acceptsMimeTypes(['audio/mpeg', 'audio/wav'])
            ->useDisk('public')
            ->singleFile()
            ->registerMediaConversions(function () {
                $this->addMediaConversion('web')
                    ->format('mp3')
                    ->extractAudio();
            });

        // Slow pronunciation for learning
        $this->addMediaCollection('slow_pronunciation')
            ->acceptsMimeTypes(['audio/mpeg', 'audio/wav'])
            ->useDisk('public')
            ->singleFile()
            ->registerMediaConversions(function () {
                $this->addMediaConversion('web')
                    ->format('mp3')
                    ->extractAudio();
            });
    }

    /**
     * Get pronunciation URL for either normal or slow speed.
     */
    public function getPronunciationUrl(bool $slow = false): ?string
    {
        $collection = $slow ? 'slow_pronunciation' : 'pronunciation';
        $media = $this->getFirstMedia($collection);
        return $media ? $media->getUrl('web') : null;
    }

    /**
     * Get preview data for the translation.
     */
    public function getPreviewData(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'pronunciation_key' => $this->pronunciation_key,
            'context_notes' => $this->context_notes,
            'language' => [
                'code' => $this->language->code,
                'name' => $this->language->name
            ],
            'media' => [
                'pronunciation' => [
                    'normal' => $this->getPronunciationUrl(false),
                    'slow' => $this->getPronunciationUrl(true)
                ]
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}