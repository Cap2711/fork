<?php

namespace App\Models;

use App\Models\Traits\HasMedia;
use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordTranslation extends Model
{
    use HasFactory, HasMedia, HasAuditLog;

    const AUDIT_AREA = 'word_translations';

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

    /**
     * Get the word being translated.
     */
    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
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
    }

    /**
     * Get pronunciation URL.
     */
    public function getPronunciationUrl(): ?string
    {
        $media = $this->getFirstMedia('pronunciation');
        return $media ? $media->getUrl('web') : null;
    }

    /**
     * Add a usage example.
     */
    public function addUsageExample(string $example, string $translation, string $type = 'common'): void
    {
        $examples = $this->usage_examples ?? [];
        $examples[] = [
            'example' => $example,
            'translation' => $translation,
            'type' => $type,
            'added_at' => now()->toDateTimeString()
        ];

        $this->usage_examples = $examples;
        $this->save();
    }

    /**
     * Remove a usage example.
     */
    public function removeUsageExample(int $index): void
    {
        if (isset($this->usage_examples[$index])) {
            $examples = $this->usage_examples;
            array_splice($examples, $index, 1);
            $this->usage_examples = $examples;
            $this->save();
        }
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
            'usage_examples' => $this->usage_examples,
            'translation_order' => $this->translation_order,
            'language' => [
                'code' => $this->language->code,
                'name' => $this->language->name
            ],
            'pronunciation_url' => $this->getPronunciationUrl(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Scope a query to include specific types of examples.
     */
    public function scopeWithExampleType($query, string $type)
    {
        return $query->whereRaw(
            "JSON_SEARCH(usage_examples, 'one', ?) IS NOT NULL",
            [$type]
        );
    }

    /**
     * Scope a query to order by most common translations first.
     */
    public function scopeByCommonUsage($query)
    {
        return $query->orderBy('translation_order');
    }
}