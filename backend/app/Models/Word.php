<?php

namespace App\Models;

use App\Models\Traits\HasMedia;
use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasManyThrough};

class Word extends Model
{
    use HasFactory, HasMedia, HasAuditLog, HasVersions;

    const AUDIT_AREA = 'words';

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

    protected array $versionedAttributes = [
        'text',
        'pronunciation_key',
        'part_of_speech',
        'metadata'
    ];

    /**
     * Get the language this word belongs to.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get translations for this word.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(WordTranslation::class)
            ->orderBy('translation_order');
    }

    /**
     * Get sentences that use this word.
     */
    public function sentences(): HasManyThrough
    {
        return $this->hasManyThrough(
            Sentence::class,
            SentenceWord::class,
            'word_id',
            'id',
            'id',
            'sentence_id'
        );
    }

    /**
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        // Pronunciation audio in source language
        $this->addMediaCollection('pronunciation')
            ->acceptsMimeTypes(['audio/mpeg', 'audio/wav'])
            ->useDisk('public')
            ->singleFile()
            ->registerMediaConversions(function () {
                $this->addMediaConversion('web')
                    ->format('mp3')
                    ->extractAudio();
            });

        // Visual aids or contextual images
        $this->addMediaCollection('visuals')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif'])
            ->useDisk('public')
            ->singleFile()
            ->registerMediaConversions(function () {
                $this->addMediaConversion('thumbnail')
                    ->width(200)
                    ->height(200);
            });
    }

    /**
     * Get pronunciation URL with optional language code.
     */
    public function getPronunciationUrl(?string $languageCode = null): ?string
    {
        $collection = $languageCode 
            ? "pronunciation_{$languageCode}" 
            : 'pronunciation';

        $media = $this->getFirstMedia($collection);
        return $media ? $media->getUrl('web') : null;
    }

    /**
     * Add pronunciation for a specific language.
     */
    public function addPronunciation(string $languageCode, $file): void
    {
        $collection = "pronunciation_{$languageCode}";
        
        // Clear existing pronunciation if any
        $this->clearMediaCollection($collection);
        
        // Add new pronunciation
        $this->addMedia($file)
            ->usingFileName("{$this->id}_{$languageCode}.mp3")
            ->toMediaCollection($collection);
    }

    /**
     * Get preview data for the word.
     */
    public function getPreviewData(string $targetLanguageCode = 'en'): array
    {
        $targetLanguage = Language::where('code', $targetLanguageCode)->first();

        return [
            'id' => $this->id,
            'text' => $this->text,
            'pronunciation_key' => $this->pronunciation_key,
            'part_of_speech' => $this->part_of_speech,
            'language' => $this->language->code,
            'translations' => $targetLanguage ? 
                $this->translations()
                    ->where('language_id', $targetLanguage->id)
                    ->get()
                    ->map(fn($t) => [
                        'text' => $t->text,
                        'context_notes' => $t->context_notes,
                        'usage_examples' => $t->usage_examples
                    ]) : [],
            'media' => [
                'pronunciation' => [
                    'source' => $this->getPronunciationUrl(),
                    'target' => $this->getPronunciationUrl($targetLanguageCode)
                ],
                'visual' => $this->getFirstMediaUrl('visuals', 'thumbnail')
            ],
            'metadata' => $this->metadata
        ];
    }

    /**
     * Get similar words (for suggestions/corrections).
     */
    public function getSimilarWords(int $limit = 5): array
    {
        return static::query()
            ->where('language_id', $this->language_id)
            ->where('id', '!=', $this->id)
            ->where('text', 'LIKE', substr($this->text, 0, 3) . '%')
            ->limit($limit)
            ->get()
            ->map(fn($w) => [
                'id' => $w->id,
                'text' => $w->text,
                'translation' => $w->translations->first()?->text
            ])
            ->toArray();
    }
}