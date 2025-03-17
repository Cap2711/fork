<?php

namespace App\Models;

use App\Models\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SentenceWord extends Model
{
    use HasFactory, HasAuditLog;

    const AUDIT_AREA = 'sentence_words';

    protected $fillable = [
        'sentence_id',
        'word_id',
        'position',
        'start_time',
        'end_time',
        'metadata'
    ];

    protected $casts = [
        'position' => 'integer',
        'start_time' => 'float',
        'end_time' => 'float',
        'metadata' => 'array'
    ];

    /**
     * Get the sentence this word belongs to.
     */
    public function sentence(): BelongsTo
    {
        return $this->belongsTo(Sentence::class);
    }

    /**
     * Get the word.
     */
    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }

    /**
     * Check if timing is set.
     */
    public function hasTimingInfo(): bool
    {
        return !is_null($this->start_time) && !is_null($this->end_time);
    }

    /**
     * Get duration in seconds.
     */
    public function getDuration(): float
    {
        return $this->hasTimingInfo() 
            ? $this->end_time - $this->start_time 
            : 0;
    }

    /**
     * Update timing information.
     */
    public function updateTiming(float $start, float $end, ?array $metadata = null): void
    {
        $this->update([
            'start_time' => $start,
            'end_time' => $end,
            'metadata' => $metadata ?? $this->metadata
        ]);
    }

    /**
     * Get word info including translation.
     */
    public function getWordInfo(string $targetLanguageCode = 'en'): array
    {
        $targetLanguage = Language::where('code', $targetLanguageCode)->first();
        $translation = $targetLanguage ? 
            $this->word->translations()
                ->where('language_id', $targetLanguage->id)
                ->orderBy('translation_order')
                ->first() : null;

        return [
            'id' => $this->word->id,
            'text' => $this->word->text,
            'position' => $this->position,
            'translation' => $translation ? [
                'text' => $translation->text,
                'context_notes' => $translation->context_notes,
                'pronunciation_url' => $translation->getPronunciationUrl()
            ] : null,
            'timing' => [
                'start' => $this->start_time,
                'end' => $this->end_time,
                'duration' => $this->getDuration()
            ],
            'pronunciation_url' => $this->word->getPronunciationUrl(),
            'metadata' => $this->metadata
        ];
    }

    /**
     * Get previous word in sentence.
     */
    public function getPreviousWord(): ?self
    {
        return static::where('sentence_id', $this->sentence_id)
            ->where('position', $this->position - 1)
            ->first();
    }

    /**
     * Get next word in sentence.
     */
    public function getNextWord(): ?self
    {
        return static::where('sentence_id', $this->sentence_id)
            ->where('position', $this->position + 1)
            ->first();
    }

    /**
     * Check if word timing overlaps with adjacent words.
     */
    public function hasTimingOverlap(): bool
    {
        if (!$this->hasTimingInfo()) {
            return false;
        }

        $previous = $this->getPreviousWord();
        if ($previous && $previous->hasTimingInfo()) {
            if ($this->start_time < $previous->end_time) {
                return true;
            }
        }

        $next = $this->getNextWord();
        if ($next && $next->hasTimingInfo()) {
            if ($this->end_time > $next->start_time) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate timing values.
     */
    public function validateTiming(): bool
    {
        if (!$this->hasTimingInfo()) {
            return true;
        }

        // Check basic constraints
        if ($this->start_time < 0 || 
            $this->end_time <= $this->start_time || 
            $this->getDuration() > 10) { // Max 10 seconds per word
            return false;
        }

        // Check for overlaps
        return !$this->hasTimingOverlap();
    }
}