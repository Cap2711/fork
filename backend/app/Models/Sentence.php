<?php

namespace App\Models;

use App\Models\Traits\HasMedia;
use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, BelongsToMany};

class Sentence extends Model
{
    use HasFactory, HasMedia, HasAuditLog, HasVersions;

    const AUDIT_AREA = 'sentences';

    protected $fillable = [
        'language_id',
        'text',
        'pronunciation_key',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    protected array $versionedAttributes = [
        'text',
        'pronunciation_key',
        'metadata'
    ];

    /**
     * Get the language this sentence belongs to.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get translations of this sentence.
     */
    public function translations(): HasMany
    {
        return $this->hasMany(SentenceTranslation::class);
    }

    /**
     * Get the words in this sentence with their order and timing.
     */
    public function words(): BelongsToMany
    {
        return $this->belongsToMany(Word::class, 'sentence_words')
            ->withPivot(['position', 'start_time', 'end_time', 'metadata'])
            ->orderBy('position');
    }

    /**
     * Register media collections.
     */
    public function registerMediaCollections(): void
    {
        // Source language pronunciation
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
     * Get word timing data.
     */
    public function getWordTimings(): array
    {
        return $this->words()
            ->get()
            ->map(fn($word) => [
                'id' => $word->id,
                'text' => $word->text,
                'position' => $word->pivot->position,
                'start_time' => $word->pivot->start_time,
                'end_time' => $word->pivot->end_time,
                'metadata' => $word->pivot->metadata
            ])
            ->toArray();
    }

    /**
     * Update word timings in the sentence.
     */
    public function updateWordTimings(array $timings): void
    {
        foreach ($timings as $timing) {
            $this->words()
                ->updateExistingPivot($timing['word_id'], [
                    'start_time' => $timing['start_time'],
                    'end_time' => $timing['end_time'],
                    'metadata' => $timing['metadata'] ?? null
                ]);
        }
    }

    /**
     * Get preview data for the sentence.
     */
    public function getPreviewData(string $targetLanguageCode = 'en'): array
    {
        $targetLanguage = Language::where('code', $targetLanguageCode)->first();

        return [
            'id' => $this->id,
            'text' => $this->text,
            'pronunciation_key' => $this->pronunciation_key,
            'language' => $this->language->code,
            'translations' => $targetLanguage ? 
                $this->translations()
                    ->where('language_id', $targetLanguage->id)
                    ->get()
                    ->map(fn($t) => [
                        'text' => $t->text,
                        'context_notes' => $t->context_notes
                    ]) : [],
            'words' => $this->getWordTimings(),
            'media' => [
                'pronunciation' => [
                    'normal' => $this->getPronunciationUrl(false),
                    'slow' => $this->getPronunciationUrl(true)
                ]
            ],
            'metadata' => $this->metadata
        ];
    }

    /**
     * Get similar sentences for suggestions.
     */
    public function getSimilarSentences(int $limit = 5): array
    {
        // Get sentences that share the most words with this one
        $wordIds = $this->words()->pluck('words.id');
        
        return static::query()
            ->where('language_id', $this->language_id)
            ->where('id', '!=', $this->id)
            ->whereHas('words', function ($query) use ($wordIds) {
                $query->whereIn('words.id', $wordIds);
            })
            ->withCount(['words' => function ($query) use ($wordIds) {
                $query->whereIn('words.id', $wordIds);
            }])
            ->orderByDesc('words_count')
            ->limit($limit)
            ->get()
            ->map(fn($s) => [
                'id' => $s->id,
                'text' => $s->text,
                'shared_words' => $s->words_count
            ])
            ->toArray();
    }
}