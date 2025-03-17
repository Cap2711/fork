<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Word, WordTranslation, Language};
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class AdminWordController extends Controller
{
    /**
     * Display a listing of words.
     */
    public function index(Request $request)
    {
        $query = Word::with(['language', 'translations']);

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

        // Filter by part of speech
        if ($request->has('part_of_speech')) {
            $query->where('part_of_speech', $request->part_of_speech);
        }

        $words = $query->paginate($request->per_page ?? 25);
        $targetLanguage = $request->target_language ?? 'en';

        return response()->json([
            'success' => true,
            'data' => $words->collect()->map(function ($word) use ($targetLanguage) {
                return $word->getPreviewData($targetLanguage);
            }),
            'meta' => [
                'current_page' => $words->currentPage(),
                'last_page' => $words->lastPage(),
                'per_page' => $words->perPage(),
                'total' => $words->total()
            ]
        ]);
    }

    /**
     * Store a new word.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'language_id' => 'required|exists:languages,id',
            'text' => [
                'required',
                'string',
                'max:255',
                Rule::unique('words')->where(function ($query) use ($request) {
                    return $query->where('language_id', $request->language_id)
                        ->where('part_of_speech', $request->part_of_speech);
                })
            ],
            'pronunciation_key' => 'nullable|string|max:255',
            'part_of_speech' => 'nullable|string|max:50',
            'metadata' => 'nullable|array',
            'translations' => 'array',
            'translations.*.language_id' => 'required|exists:languages,id',
            'translations.*.text' => 'required|string|max:255',
            'translations.*.pronunciation_key' => 'nullable|string|max:255',
            'translations.*.context_notes' => 'nullable|string',
            'translations.*.usage_examples' => 'nullable|array',
            'pronunciation_audio' => 'nullable|file|mimes:mp3,wav|max:10240'
        ]);

        $word = DB::transaction(function () use ($validated, $request) {
            $word = Word::create([
                'language_id' => $validated['language_id'],
                'text' => $validated['text'],
                'pronunciation_key' => $validated['pronunciation_key'] ?? null,
                'part_of_speech' => $validated['part_of_speech'] ?? null,
                'metadata' => $validated['metadata'] ?? null
            ]);

            // Add translations if provided
            if (!empty($validated['translations'])) {
                foreach ($validated['translations'] as $index => $translation) {
                    $word->translations()->create([
                        'language_id' => $translation['language_id'],
                        'text' => $translation['text'],
                        'pronunciation_key' => $translation['pronunciation_key'] ?? null,
                        'context_notes' => $translation['context_notes'] ?? null,
                        'usage_examples' => $translation['usage_examples'] ?? null,
                        'translation_order' => $index + 1
                    ]);
                }
            }

            // Handle pronunciation audio if provided
            if ($request->hasFile('pronunciation_audio')) {
                $word->addMedia($request->file('pronunciation_audio'))
                    ->usingFileName("{$word->id}_pronunciation.mp3")
                    ->toMediaCollection('pronunciation');
            }

            return $word;
        });

        return response()->json([
            'success' => true,
            'data' => $word->getPreviewData()
        ], 201);
    }

    /**
     * Display the specified word.
     */
    public function show(Word $word, Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $word->getPreviewData($request->target_language ?? 'en')
        ]);
    }

    /**
     * Update the specified word.
     */
    public function update(Request $request, Word $word)
    {
        $validated = $request->validate([
            'text' => [
                'string',
                'max:255',
                Rule::unique('words')->where(function ($query) use ($request, $word) {
                    return $query->where('language_id', $word->language_id)
                        ->where('part_of_speech', $request->part_of_speech ?? $word->part_of_speech)
                        ->where('id', '!=', $word->id);
                })
            ],
            'pronunciation_key' => 'nullable|string|max:255',
            'part_of_speech' => 'nullable|string|max:50',
            'metadata' => 'nullable|array'
        ]);

        $word->update($validated);

        return response()->json([
            'success' => true,
            'data' => $word->fresh()->getPreviewData()
        ]);
    }

    /**
     * Add a translation to a word.
     */
    public function addTranslation(Request $request, Word $word)
    {
        $validated = $request->validate([
            'language_id' => [
                'required',
                'exists:languages,id',
                Rule::unique('word_translations')->where(function ($query) use ($word) {
                    return $query->where('word_id', $word->id);
                })
            ],
            'text' => 'required|string|max:255',
            'pronunciation_key' => 'nullable|string|max:255',
            'context_notes' => 'nullable|string',
            'usage_examples' => 'nullable|array',
            'pronunciation_audio' => 'nullable|file|mimes:mp3,wav|max:10240'
        ]);

        $translation = DB::transaction(function () use ($word, $validated, $request) {
            $translation = $word->translations()->create([
                'language_id' => $validated['language_id'],
                'text' => $validated['text'],
                'pronunciation_key' => $validated['pronunciation_key'] ?? null,
                'context_notes' => $validated['context_notes'] ?? null,
                'usage_examples' => $validated['usage_examples'] ?? null,
                'translation_order' => $word->translations()->count() + 1
            ]);

            if ($request->hasFile('pronunciation_audio')) {
                $translation->addMedia($request->file('pronunciation_audio'))
                    ->usingFileName("{$word->id}_{$translation->id}_pronunciation.mp3")
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
     * Update a word translation.
     */
    public function updateTranslation(Request $request, Word $word, WordTranslation $translation)
    {
        if ($translation->word_id !== $word->id) {
            throw new AuthorizationException('This translation does not belong to the specified word.');
        }

        $validated = $request->validate([
            'text' => 'string|max:255',
            'pronunciation_key' => 'nullable|string|max:255',
            'context_notes' => 'nullable|string',
            'usage_examples' => 'nullable|array'
        ]);

        $translation->update($validated);

        return response()->json([
            'success' => true,
            'data' => $translation->fresh()->getPreviewData()
        ]);
    }

    /**
     * Upload pronunciation audio for a word.
     */
    public function uploadAudio(Request $request, Word $word)
    {
        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav|max:10240'
        ]);

        $word->clearMediaCollection('pronunciation');
        $word->addMedia($request->file('audio'))
            ->usingFileName("{$word->id}_pronunciation.mp3")
            ->toMediaCollection('pronunciation');

        return response()->json([
            'success' => true,
            'data' => [
                'pronunciation_url' => $word->getPronunciationUrl()
            ]
        ]);
    }

    /**
     * Upload pronunciation audio for a translation.
     */
    public function uploadTranslationAudio(Request $request, Word $word, WordTranslation $translation)
    {
        if ($translation->word_id !== $word->id) {
            throw new AuthorizationException('This translation does not belong to the specified word.');
        }

        $request->validate([
            'audio' => 'required|file|mimes:mp3,wav|max:10240'
        ]);

        $translation->clearMediaCollection('pronunciation');
        $translation->addMedia($request->file('audio'))
            ->usingFileName("{$word->id}_{$translation->id}_pronunciation.mp3")
            ->toMediaCollection('pronunciation');

        return response()->json([
            'success' => true,
            'data' => [
                'pronunciation_url' => $translation->getPronunciationUrl()
            ]
        ]);
    }
}