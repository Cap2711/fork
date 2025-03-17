<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use App\Models\Traits\HasVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\{HasMany, BelongsToMany};

class Language extends Model
{
    use HasFactory, HasAuditLog, HasVersions;

    const AUDIT_AREA = 'languages';

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Get words in this language.
     */
    public function words(): HasMany
    {
        return $this->hasMany(Word::class);
    }

    /**
     * Get sentences in this language.
     */
    public function sentences(): HasMany
    {
        return $this->hasMany(Sentence::class);
    }

    /**
     * Get languages that this language can be learned from.
     */
    public function sourceLanguages(): BelongsToMany
    {
        return $this->belongsToMany(
            Language::class,
            'language_pairs',
            'target_language_id',
            'source_language_id'
        )->where('is_active', true);
    }

    /**
     * Get languages that can be learned from this language.
     */
    public function targetLanguages(): BelongsToMany
    {
        return $this->belongsToMany(
            Language::class,
            'language_pairs',
            'source_language_id',
            'target_language_id'
        )->where('is_active', true);
    }

    /**
     * Check if this language can be learned from another language.
     */
    public function canBeLearnedFrom(Language $sourceLanguage): bool
    {
        return $this->sourceLanguages()
            ->where('languages.id', $sourceLanguage->id)
            ->exists();
    }

    /**
     * Get all available word translations for this language.
     */
    public function wordTranslations(): HasMany
    {
        return $this->hasMany(WordTranslation::class);
    }

    /**
     * Get all available sentence translations for this language.
     */
    public function sentenceTranslations(): HasMany
    {
        return $this->hasMany(SentenceTranslation::class);
    }

    /**
     * Get preview data for the language.
     */
    public function getPreviewData(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'native_name' => $this->native_name,
            'is_active' => $this->is_active,
            'stats' => [
                'words_count' => $this->words()->count(),
                'sentences_count' => $this->sentences()->count(),
                'source_languages' => $this->sourceLanguages()
                    ->select('code', 'name')
                    ->get(),
                'target_languages' => $this->targetLanguages()
                    ->select('code', 'name')
                    ->get()
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Get ISO code list (for form validation).
     */
    public static function getValidIsoCodes(): array
    {
        return [
            'en', 'es', 'fr', 'de', 'it', 'pt', 'ru', 'ja', 'ko', 'zh',
            'ar', 'hi', 'tr', 'pl', 'nl', 'vi', 'th', 'id', 'sv', 'da',
            'fi', 'nb', 'hu', 'cs', 'el', 'he', 'ro', 'uk'
        ];
    }
}