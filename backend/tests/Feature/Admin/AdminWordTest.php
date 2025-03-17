<?php

namespace Tests\Feature\Admin;

use App\Models\{Language, Word, WordTranslation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Admin\AdminTestCase;

class AdminWordTest extends AdminTestCase
{
    use RefreshDatabase, WithFaker;

    private Language $sourceLanguage;
    private Language $targetLanguage;

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
    }

    public function test_unauthorized_user_cannot_access_words()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/words');

        $this->assertUnauthorized($response);
    }

    public function test_admin_can_list_words()
    {
        $word = Word::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは',
            'pronunciation_key' => 'kon-ni-chi-wa',
            'part_of_speech' => 'greeting'
        ]);

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/words');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'text' => 'こんにちは',
                        'pronunciation_key' => 'kon-ni-chi-wa',
                        'part_of_speech' => 'greeting'
                    ]
                ]
            ]);
    }

    public function test_admin_can_create_word()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/words', [
                'language_id' => $this->sourceLanguage->id,
                'text' => 'さようなら',
                'pronunciation_key' => 'sa-yo-u-na-ra',
                'part_of_speech' => 'greeting',
                'metadata' => ['usage_level' => 'formal']
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'text' => 'さようなら',
                    'pronunciation_key' => 'sa-yo-u-na-ra',
                    'part_of_speech' => 'greeting'
                ]
            ]);

        $this->assertDatabaseHas('words', [
            'language_id' => $this->sourceLanguage->id,
            'text' => 'さようなら',
            'pronunciation_key' => 'sa-yo-u-na-ra',
            'part_of_speech' => 'greeting'
        ]);
    }

    public function test_admin_cannot_create_duplicate_word()
    {
        Word::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは',
            'pronunciation_key' => 'kon-ni-chi-wa',
            'part_of_speech' => 'greeting'
        ]);

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/words', [
                'language_id' => $this->sourceLanguage->id,
                'text' => 'こんにちは',
                'pronunciation_key' => 'kon-ni-chi-wa',
                'part_of_speech' => 'greeting'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['text']);
    }

    public function test_admin_can_update_word()
    {
        $word = Word::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは',
            'pronunciation_key' => 'kon-ni-chi-wa',
            'part_of_speech' => 'greeting'
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/words/{$word->id}", [
                'pronunciation_key' => 'kon-ni-chi-wa-updated',
                'part_of_speech' => 'common_greeting',
                'metadata' => ['usage_level' => 'casual']
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'text' => 'こんにちは',
                    'pronunciation_key' => 'kon-ni-chi-wa-updated',
                    'part_of_speech' => 'common_greeting'
                ]
            ]);

        $this->assertDatabaseHas('words', [
            'id' => $word->id,
            'pronunciation_key' => 'kon-ni-chi-wa-updated',
            'part_of_speech' => 'common_greeting'
        ]);
    }

    public function test_admin_can_add_word_translation()
    {
        $word = Word::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは',
            'pronunciation_key' => 'kon-ni-chi-wa',
            'part_of_speech' => 'greeting'
        ]);

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/words/{$word->id}/translations", [
                'language_id' => $this->targetLanguage->id,
                'text' => 'hello',
                'pronunciation_key' => 'heh-low',
                'context_notes' => 'Formal greeting during the day',
                'usage_examples' => [
                    'Hello, how are you?',
                    'Hello, nice to meet you.'
                ]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'text' => 'hello',
                    'pronunciation_key' => 'heh-low',
                    'context_notes' => 'Formal greeting during the day'
                ]
            ]);

        $this->assertDatabaseHas('word_translations', [
            'word_id' => $word->id,
            'language_id' => $this->targetLanguage->id,
            'text' => 'hello',
            'pronunciation_key' => 'heh-low',
            'context_notes' => 'Formal greeting during the day'
        ]);
    }

    public function test_admin_can_update_word_translation()
    {
        $word = Word::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは',
            'pronunciation_key' => 'kon-ni-chi-wa'
        ]);

        $translation = WordTranslation::create([
            'word_id' => $word->id,
            'language_id' => $this->targetLanguage->id,
            'text' => 'hello',
            'pronunciation_key' => 'heh-low',
            'context_notes' => 'Basic greeting'
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/words/{$word->id}/translations/{$translation->id}", [
                'text' => 'hello (updated)',
                'context_notes' => 'Updated greeting notes',
                'usage_examples' => ['New example 1', 'New example 2']
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'text' => 'hello (updated)',
                    'context_notes' => 'Updated greeting notes'
                ]
            ]);

        $this->assertDatabaseHas('word_translations', [
            'id' => $translation->id,
            'text' => 'hello (updated)',
            'context_notes' => 'Updated greeting notes'
        ]);
    }

    public function test_admin_can_delete_word_translation()
    {
        $word = Word::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは'
        ]);

        $translation = WordTranslation::create([
            'word_id' => $word->id,
            'language_id' => $this->targetLanguage->id,
            'text' => 'hello'
        ]);

        $response = $this->actingAsAdmin()
            ->deleteJson("/api/admin/words/{$word->id}/translations/{$translation->id}");

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('word_translations', [
            'id' => $translation->id
        ]);
    }

    public function test_admin_can_upload_word_audio()
    {
        $word = Word::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは',
            'pronunciation_key' => 'kon-ni-chi-wa'
        ]);

        $audio = UploadedFile::fake()->create('word.mp3', 100);

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/words/{$word->id}/audio", [
                'audio' => $audio
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'pronunciation_url' => true
                ]
            ]);

        $this->assertDatabaseHas('media_files', [
            'mediable_type' => Word::class,
            'mediable_id' => $word->id,
            'collection_name' => 'pronunciation'
        ]);
    }

    public function test_admin_can_upload_translation_audio()
    {
        $word = Word::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは'
        ]);

        $translation = WordTranslation::create([
            'word_id' => $word->id,
            'language_id' => $this->targetLanguage->id,
            'text' => 'hello'
        ]);

        $audio = UploadedFile::fake()->create('translation.mp3', 100);

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/words/{$word->id}/translations/{$translation->id}/audio", [
                'audio' => $audio
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'pronunciation_url' => true
                ]
            ]);

        $this->assertDatabaseHas('media_files', [
            'mediable_type' => WordTranslation::class,
            'mediable_id' => $translation->id,
            'collection_name' => 'pronunciation'
        ]);
    }

    public function test_admin_cannot_upload_invalid_audio_file()
    {
        $word = Word::create([
            'language_id' => $this->sourceLanguage->id,
            'text' => 'こんにちは'
        ]);

        $notAudio = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAsAdmin()
            ->postJson("/api/admin/words/{$word->id}/audio", [
                'audio' => $notAudio
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['audio']);
    }
}