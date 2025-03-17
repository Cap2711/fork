<?php

namespace Tests\Feature\Admin;

use App\Models\{Language, Sentence, SentenceTranslation, Word, SentenceWord};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admin\AdminTestCase;

class AdminSentenceTest extends AdminTestCase
{
    use RefreshDatabase, WithFaker;

    private Language $sourceLanguage;
    private Language $targetLanguage;
    private Word $word;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->sourceLanguage = Language::create([
            'code' => 'ja',
            'name' => 'Japanese',
            'native_name' => '日本語'
        ]);

        $this->targetLanguage = Language::create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English'
        ]);

        $this->word = Word::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは',
            'pronunciation_key' => 'kon-ni-chi-wa'
        ]);
    }

    public function test_unauthorized_user_cannot_access_sentences()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/sentences');

        $this->assertUnauthorized($response);
    }

    public function test_admin_can_list_sentences()
    {
        $sentence = Sentence::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは、元気ですか？',
            'pronunciation_key' => 'kon-ni-chi-wa, gen-ki de-su-ka?',
            'metadata' => ['difficulty' => 'beginner']
        ]);

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/sentences');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'text' => 'こんにちは、元気ですか？',
                        'pronunciation_key' => 'kon-ni-chi-wa, gen-ki de-su-ka?'
                    ]
                ]
            ]);
    }

    public function test_admin_can_create_sentence()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/sentences', [
                'language_id' => $this->sourceLanguage->id,
                'text' => 'おはようございます。',
                'pronunciation_key' => 'o-ha-yo-u go-za-i-ma-su',
                'metadata' => [
                    'difficulty' => 'beginner',
                    'tags' => ['greeting', 'morning']
                ]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'text' => 'おはようございます。',
                    'pronunciation_key' => 'o-ha-yo-u go-za-i-ma-su'
                ]
            ]);

        $this->assertDatabaseHas('sentences', [
            'language_id' => $this->sourceLanguage->id,
            'text' => 'おはようございます。'
        ]);
    }

    public function test_admin_can_update_sentence()
    {
        $sentence = Sentence::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは。',
            'pronunciation_key' => 'kon-ni-chi-wa',
            'metadata' => ['difficulty' => 'beginner']
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/sentences/{$sentence->id}", [
                'text' => 'こんにちは！',
                'pronunciation_key' => 'kon-ni-chi-wa!',
                'metadata' => [
                    'difficulty' => 'beginner',
                    'tags' => ['greeting', 'casual']
                ]
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'text' => 'こんにちは！',
                    'pronunciation_key' => 'kon-ni-chi-wa!'
                ]
            ]);
    }

    public function test_admin_can_add_sentence_translation()
    {
        $sentence = Sentence::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは、元気ですか？',
            'pronunciation_key' => 'kon-ni-chi-wa, gen-ki de-su-ka?'
        ]);

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/sentences/{$sentence->id}/translations", [
                'language_id' => $this->targetLanguage->id,
                'text' => 'Hello, how are you?',
                'pronunciation_key' => 'he-low, how ar yu?',
                'context_notes' => 'Casual greeting with inquiry about wellbeing'
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'text' => 'Hello, how are you?',
                    'pronunciation_key' => 'he-low, how ar yu?',
                    'context_notes' => 'Casual greeting with inquiry about wellbeing'
                ]
            ]);

        $this->assertDatabaseHas('sentence_translations', [
            'sentence_id' => $sentence->id,
            'language_id' => $this->targetLanguage->id,
            'text' => 'Hello, how are you?'
        ]);
    }

    public function test_admin_can_update_word_timings()
    {
        $sentence = Sentence::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは、元気ですか？'
        ]);

        $wordTiming = [
            'word_id' => $this->word->id,
            'position' => 1,
            'start_time' => 0.0,
            'end_time' => 1.2,
            'metadata' => ['emphasis' => 'normal']
        ];

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/sentences/{$sentence->id}/word-timings", [
                'timings' => [$wordTiming]
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true
            ]);

        $this->assertDatabaseHas('sentence_words', [
            'sentence_id' => $sentence->id,
            'word_id' => $this->word->id,
            'position' => 1,
            'start_time' => 0.0,
            'end_time' => 1.2
        ]);
    }

    public function test_admin_can_reorder_sentence_words()
    {
        $sentence = Sentence::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは、元気ですか？'
        ]);

        $word1 = Word::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは'
        ]);

        $word2 = Word::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => '元気'
        ]);

        SentenceWord::create([
            'sentence_id' => $sentence->id,
            'word_id' => $word1->id,
            'position' => 1
        ]);

        SentenceWord::create([
            'sentence_id' => $sentence->id,
            'word_id' => $word2->id,
            'position' => 2
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/sentences/{$sentence->id}/words/reorder", [
                'words' => [
                    ['id' => $word2->id, 'position' => 1],
                    ['id' => $word1->id, 'position' => 2]
                ]
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('sentence_words', [
            'sentence_id' => $sentence->id,
            'word_id' => $word2->id,
            'position' => 1
        ]);

        $this->assertDatabaseHas('sentence_words', [
            'sentence_id' => $sentence->id,
            'word_id' => $word1->id,
            'position' => 2
        ]);
    }

    public function test_admin_can_upload_sentence_audio()
    {
        $sentence = Sentence::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは、元気ですか？'
        ]);

        $audio = UploadedFile::fake()->create('sentence.mp3', 100);

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/sentences/{$sentence->id}/audio", [
                'audio' => $audio
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'audio_url' => true
                ]
            ]);

        $this->assertDatabaseHas('media_files', [
            'mediable_type' => Sentence::class,
            'mediable_id' => $sentence->id,
            'collection_name' => 'audio'
        ]);
    }

    public function test_admin_can_upload_slow_sentence_audio()
    {
        $sentence = Sentence::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは、元気ですか？'
        ]);

        $audio = UploadedFile::fake()->create('sentence-slow.mp3', 100);

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/sentences/{$sentence->id}/audio-slow", [
                'audio' => $audio
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'audio_url' => true
                ]
            ]);

        $this->assertDatabaseHas('media_files', [
            'mediable_type' => Sentence::class,
            'mediable_id' => $sentence->id,
            'collection_name' => 'audio_slow'
        ]);
    }

    public function test_admin_cannot_upload_invalid_audio_file()
    {
        $sentence = Sentence::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは、元気ですか？'
        ]);

        $notAudio = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/sentences/{$sentence->id}/audio", [
                'audio' => $notAudio
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio']);
    }
}