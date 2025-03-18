<?php

namespace App\Models;

use App\Models\Traits\{HasVersions, HasAuditLog};
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Language extends Model
{
    use HasFactory, HasAuditLog, HasVersions;

    protected $fillable = [
        'code',
        'name',
        'native_name',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    protected array $auditLogEvents = [
        'created' => 'Created new language: :code (:name)',
        'updated' => 'Updated language: :code',
        'deleted' => 'Deleted language: :code',
    ];

    protected array $auditLogProperties = [
        'code',
        'name',
        'native_name',
        'is_active'
    ];

    /**
     * Get the words in this language.
     */
    public function words(): HasMany
    {
        return $this->hasMany(Word::class);
    }

    /**
     * Get the sentences in this language.
     */
    public function sentences(): HasMany
    {
        return $this->hasMany(Sentence::class);
    }

    /**
     * Get language pairs where this is the source language.
     */
    public function sourceLanguagePairs(): HasMany
    {
        return $this->hasMany(LanguagePair::class, 'source_language_id');
    }

    /**
     * Get language pairs where this is the target language.
     */
    public function targetLanguagePairs(): HasMany
    {
        return $this->hasMany(LanguagePair::class, 'target_language_id');
    }
}