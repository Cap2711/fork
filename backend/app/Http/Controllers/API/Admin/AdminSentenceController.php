<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Sentence, SentenceTranslation, Word, Language};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class AdminSentenceController extends Controller
{
    /**
     * Display a listing of sentences.
     */
    public function index(Request $request)
    {
        $query = Sentence::with(['language', 'translations', 'words']);

        // Filter by language
        if ($request->has('language')) {
            $query->whereHas('language', function ($q) use ($request) {
                $q->where('code', $request->language);
            });
        }

        // Filter by search term
        if ($request->has('search')) {
            $query->where('text', 'like', "%{$request->search}%")
                ->orWhereHas('translations', function ($q) use ($request) {
                    $q->where('text', 'like', "%{$request->search}%");
                });
        }

        // Filter by difficulty level
        if ($request->has('difficulty')) {
            $query->whereJsonContains('metadata->difficulty', $request->difficulty);
        }

        $sentences = $query->paginate($request->per_page ?? 25);
        $targetLanguage = $request->target_language ?? 'en';

        return response()->json([
            'success' => true,
            'data' => $sentences->collect()->map(function ($sentence) use ($targetLanguage) {
                return $sentence->getPreviewData($targetLanguage);
            }),
            'meta' => [
                'current_page' => $sentences->currentPage(),
                'last_page' => $sentences->lastPage(),
                'per_page' => $sentences->perPage(),
                'total' => $sentences->total()
            ]
        ]);
    }

    /**
     * Store a new sentence.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'language_id' => 'required|exists:languages,id',
            'text' => 'required|string|max:1000',
            'pronunciation_key' => 'nullable|string',
            'metadata' => 'nullable|array',
            'words' => 'required|array|min:1',
            'words.*.word_id' => 'required|exists:words,id',
            'words.*.position' => 'required|integer|min:0',
            'translations' => 'array',
            'translations.*.language_id' => 'required|exists:languages,id',
            'translations.*.text' => 'required|string|max:1000',
            'translations.*.pronunciation_key' => 'nullable|string',
            'translations.*.context_notes' => 'nullable|string',
            'audio' => 'nullable|file|mimes:mp3,wav|max:10240',
            'audio_slow' => 'nullable|file|mimes:mp3,wav|max:10240'
        ]);

        $sentence = DB::transaction(function () use ($validated, $request) {
            $sentence = Sentence::create([
                'language_id' => $validated['language_id'],
                'text' => $validated['text'],
                'pronunciation_key' => $validated['pronunciation_key'] ?? null,
                'metadata' => $validated['metadata'] ?? null
            ]);

            // Add words with positions
            foreach ($validated['words'] as $wordData) {
                $sentence->words()->attach($wordData['word_id'], [
                    'position' => $wordData['position']
                ]);
            }

            // Add translations if provided
            if (!empty($validated['translations'])) {
                foreach ($validated['translations'] as $translation) {
                    $sentence->translations()->create([
                        'language_id' => $translation['language_id'],
                        'text' => $translation['text'],
                        'pronunciation_key' => $translation['pronunciation_key'] ?? null,
                        'context_notes' => $translation['context_notes'] ?? null
                    ]);
                }
            }

            // Handle audio files
            if ($request->hasFile('audio')) {
                $sentence->addMedia($request->file('audio'))
                    ->usingFileName("{$sentence->id}_pronunciation.mp3")
                    ->toMediaCollection('pronunciation');
            }

            if ($request->hasFile('audio_slow')) {
                $sentence->addMedia($request->file('audio_slow'))
                    ->usingFileName("{$sentence->id}_pronunciation_slow.mp3")
                    ->toMediaCollection('slow_pronunciation');
            }

            return $sentence;
        });

        return response()->json([
            'success' => true,
            'data' => $sentence->getPreviewData()
        ], 201);
    }

    /**
     * Update word timings in a sentence.
     */
    public function updateWordTimings(Request $request, Sentence $sentence)
    {
        $validated = $request->validate([
            'timings' => 'required|array',
            'timings.*.word_id' => [
                'required',
                'integer',
                Rule::exists('sentence_words', 'word_id')->where(function ($query) use ($sentence) {
                    $query->where('sentence_id', $sentence->id);
                })
            ],
            'timings.*.start_time' => 'required|numeric|min:0',
            'timings.*.end_time' => 'required|numeric|gt:timings.*.start_time',
            'timings.*.metadata' => 'nullable|array'
        ]);

        DB::transaction(function () use ($sentence, $validated) {
            foreach ($validated['timings'] as $timing) {
                $sentence->words()->updateExistingPivot($timing['word_id'], [
                    'start_time' => $timing['start_time'],
                    'end_time' => $timing['end_time'],
                    'metadata' => $timing['metadata'] ?? null
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $sentence->getWordTimings()
        ]);
    }

    /**
     * Upload audio files for a sentence.
     */
    public function uploadAudio(Request $request, Sentence $sentence)
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav|max:10240'
        ]);

        $sentence->clearMediaCollection('pronunciation');
        $sentence->addMedia($request->file('audio'))
            ->usingFileName("{$sentence->id}_pronunciation.mp3")
            ->toMediaCollection('pronunciation');

        return response()->json([
            'success' => true,
            'data' => [
                'pronunciation_url' => $sentence->getPronunciationUrl(false)
            ]
        ]);
    }

    /**
     * Upload slow pronunciation audio.
     */
    public function uploadSlowAudio(Request $request, Sentence $sentence)
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav|max:10240'
        ]);

        $sentence->clearMediaCollection('slow_pronunciation');
        $sentence->addMedia($request->file('audio'))
            ->usingFileName("{$sentence->id}_pronunciation_slow.mp3")
            ->toMediaCollection('slow_pronunciation');

        return response()->json([
            'success' => true,
            'data' => [
                'pronunciation_url' => $sentence->getPronunciationUrl(true)
            ]
        ]);
    }

    /**
     * Add a translation to a sentence.
     */
    public function addTranslation(Request $request, Sentence $sentence)
    {
        $validated = $request->validate([
            'language_id' => [
                'required',
                'exists:languages,id',
                Rule::unique('sentence_translations')->where(function ($query) use ($sentence) {
                    return $query->where('sentence_id', $sentence->id);
                })
            ],
            'text' => 'required|string|max:1000',
            'pronunciation_key' => 'nullable|string',
            'context_notes' => 'nullable|string',
            'audio' => 'nullable|file|mimes:mp3,wav|max:10240'
        ]);

        $translation = DB::transaction(function () use ($sentence, $validated, $request) {
            $translation = $sentence->translations()->create([
                'language_id' => $validated['language_id'],
                'text' => $validated['text'],
                'pronunciation_key' => $validated['pronunciation_key'] ?? null,
                'context_notes' => $validated['context_notes'] ?? null
            ]);

            if ($request->hasFile('audio')) {
                $translation->addMedia($request->file('audio'))
                    ->usingFileName("{$sentence->id}_{$translation->id}_pronunciation.mp3")
                    ->toMediaCollection('pronunciation');
            }

            return $translation;
        });

        return response()->json([
            'success' => true,
            'data' => $translation->getPreviewData()
        ], 201);
    }

    /**
     * Update a sentence translation.
     */
    public function updateTranslation(Request $request, Sentence $sentence, SentenceTranslation $translation)
    {
        if ($translation->sentence_id !== $sentence->id) {
            throw new AuthorizationException('This translation does not belong to the specified sentence.');
        }

        $validated = $request->validate([
            'text' => 'string|max:1000',
            'pronunciation_key' => 'nullable|string',
            'context_notes' => 'nullable|string'
        ]);

        $translation->update($validated);

        return response()->json([
            'success' => true,
            'data' => $translation->fresh()->getPreviewData()
        ]);
    }

    /**
     * Reorder words in a sentence.
     */
    public function reorderWords(Request $request, Sentence $sentence)
    {
        $validated = $request->validate([
            'order' => [
                'required',
                'array',
                Rule::forEach(function () {
                    return [
                        'word_id' => 'required|integer',
                        'position' => 'required|integer|min:0'
                    ];
                })
            ]
        ]);

        DB::transaction(function () use ($sentence, $validated) {
            foreach ($validated['order'] as $item) {
                $sentence->words()->updateExistingPivot($item['word_id'], [
                    'position' => $item['position']
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $sentence->fresh()->getWordTimings()
        ]);
    }
}