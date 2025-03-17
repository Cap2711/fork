<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\LanguagePair;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminLanguageController extends Controller
{
    /**
     * Display a listing of languages.
     */
    public function index()
    {
        $languages = Language::with(['sourceLanguages', 'targetLanguages'])
            ->get()
            ->map->getPreviewData();

        return response()->json([
            'success' => true,
            'data' => $languages
        ]);
    }

    /**
     * Store a new language.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'size:2', Rule::in(Language::getValidIsoCodes())],
            'name' => 'required|string|max:255',
            'native_name' => 'required|string|max:255',
            'is_active' => 'boolean'
        ]);

        $language = Language::create($validated);

        return response()->json([
            'success' => true,
            'data' => $language->getPreviewData()
        ], 201);
    }

    /**
     * Display the specified language.
     */
    public function show(Language $language)
    {
        return response()->json([
            'success' => true,
            'data' => $language->getPreviewData()
        ]);
    }

    /**
     * Update the specified language.
     */
    public function update(Request $request, Language $language)
    {
        $validated = $request->validate([
            'name' => 'string|max:255',
            'native_name' => 'string|max:255',
            'is_active' => 'boolean'
        ]);

        $language->update($validated);

        return response()->json([
            'success' => true,
            'data' => $language->fresh()->getPreviewData()
        ]);
    }

    /**
     * Update language status.
     */
    public function updateStatus(Request $request, Language $language)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $language->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Language status updated successfully'
        ]);
    }

    /**
     * Create a new language pair.
     */
    public function createPair(Request $request)
    {
        $validated = $request->validate([
            'source_language_id' => 'required|exists:languages,id',
            'target_language_id' => [
                'required',
                'exists:languages,id',
                'different:source_language_id',
                Rule::unique('language_pairs', 'target_language_id')
                    ->where('source_language_id', $request->source_language_id)
            ]
        ]);

        $pair = Language::find($validated['source_language_id'])
            ->targetLanguages()
            ->attach($validated['target_language_id'], ['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Language pair created successfully'
        ], 201);
    }

    /**
     * Delete a language pair.
     */
    public function deletePair(Language $source, Language $target)
    {
        $source->targetLanguages()->detach($target->id);

        return response()->json([
            'success' => true,
            'message' => 'Language pair deleted successfully'
        ]);
    }

    /**
     * Update language pair status.
     */
    public function updatePairStatus(Request $request, Language $source, Language $target)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean'
        ]);

        $source->targetLanguages()
            ->updateExistingPivot($target->id, ['is_active' => $validated['is_active']]);

        return response()->json([
            'success' => true,
            'message' => 'Language pair status updated successfully'
        ]);
    }

    /**
     * Get language statistics.
     */
    public function getStatistics(Language $language)
    {
        $stats = [
            'word_count' => $language->words()->count(),
            'sentence_count' => $language->sentences()->count(),
            'audio_coverage' => [
                'words' => $this->calculateAudioCoverage($language, 'Word'),
                'sentences' => $this->calculateAudioCoverage($language, 'Sentence')
            ],
            'translation_coverage' => $this->getTranslationCoverage($language),
            'learner_count' => $this->getLearnerCount($language)
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Calculate audio coverage for content type.
     */
    private function calculateAudioCoverage(Language $language, string $type): array
    {
        $modelClass = "App\\Models\\$type";
        $total = $modelClass::where('language_id', $language->id)->count();
        $withAudio = $modelClass::where('language_id', $language->id)
            ->whereHas('media', function ($query) {
                $query->where('collection_name', 'pronunciation');
            })
            ->count();

        return [
            'total' => $total,
            'with_audio' => $withAudio,
            'percentage' => $total > 0 ? round(($withAudio / $total) * 100, 2) : 0
        ];
    }

    /**
     * Get translation coverage stats.
     */
    private function getTranslationCoverage(Language $language): array
    {
        $targetLanguages = $language->targetLanguages;
        $coverage = [];

        foreach ($targetLanguages as $target) {
            $wordTranslations = DB::table('word_translations')
                ->where('language_id', $target->id)
                ->whereIn('word_id', $language->words()->pluck('id'))
                ->count();

            $sentenceTranslations = DB::table('sentence_translations')
                ->where('language_id', $target->id)
                ->whereIn('sentence_id', $language->sentences()->pluck('id'))
                ->count();

            $coverage[$target->code] = [
                'language_name' => $target->name,
                'words' => [
                    'total' => $language->words()->count(),
                    'translated' => $wordTranslations,
                    'percentage' => $language->words()->count() > 0 
                        ? round(($wordTranslations / $language->words()->count()) * 100, 2) 
                        : 0
                ],
                'sentences' => [
                    'total' => $language->sentences()->count(),
                    'translated' => $sentenceTranslations,
                    'percentage' => $language->sentences()->count() > 0 
                        ? round(($sentenceTranslations / $language->sentences()->count()) * 100, 2) 
                        : 0
                ]
            ];
        }

        return $coverage;
    }

    /**
     * Get number of users learning this language.
     */
    private function getLearnerCount(Language $language): array
    {
        return [
            'total_learners' => DB::table('user_progress')
                ->where('trackable_type', 'App\Models\LearningPath')
                ->whereIn('trackable_id', function ($query) use ($language) {
                    $query->select('id')
                        ->from('learning_paths')
                        ->where('target_language_id', $language->id);
                })
                ->distinct('user_id')
                ->count('user_id'),
            'active_learners' => DB::table('user_progress')
                ->where('trackable_type', 'App\Models\LearningPath')
                ->whereIn('trackable_id', function ($query) use ($language) {
                    $query->select('id')
                        ->from('learning_paths')
                        ->where('target_language_id', $language->id);
                })
                ->where('updated_at', '>=', now()->subDays(30))
                ->distinct('user_id')
                ->count('user_id')
        ];
    }
}