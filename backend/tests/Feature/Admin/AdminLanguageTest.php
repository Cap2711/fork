<?php

namespace Tests\Feature\Admin;

use App\Models\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Admin\AdminTestCase;

class AdminLanguageTest extends AdminTestCase
{
    use RefreshDatabase;

    public function test_unauthorized_user_cannot_access_languages()
    {
        $response = $this->actingAsUser()
            ->getJson('/api/admin/languages');

        $this->assertUnauthorized($response);
    }

    public function test_admin_can_list_languages()
    {
        $language = Language::create([
            'code' => 'ja',
            'name' => 'Japanese',
            'native_name' => '日本語',
            'is_active' => true
        ]);

        $response = $this->actingAsAdmin()
            ->getJson('/api/admin/languages');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    [
                        'code' => 'ja',
                        'name' => 'Japanese',
                        'native_name' => '日本語',
                        'is_active' => true
                    ]
                ]
            ]);
    }

    public function test_admin_can_create_language()
    {
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/languages', [
                'code' => 'ko',
                'name' => 'Korean',
                'native_name' => '한국어'
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'ko',
                    'name' => 'Korean',
                    'native_name' => '한국어',
                    'is_active' => true
                ]
            ]);

        $this->assertDatabaseHas('languages', [
            'code' => 'ko',
            'name' => 'Korean',
            'native_name' => '한국어',
            'is_active' => true
        ]);
    }

    public function test_admin_cannot_create_duplicate_language_code()
    {
        Language::create([
            'code' => 'ja',
            'name' => 'Japanese',
            'native_name' => '日本語'
        ]);

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/languages', [
                'code' => 'ja',
                'name' => 'Japanese Alternative',
                'native_name' => 'にほんご'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_admin_can_update_language()
    {
        $language = Language::create([
            'code' => 'ja',
            'name' => 'Japanese',
            'native_name' => '日本語',
            'is_active' => true
        ]);

        $response = $this->actingAsAdmin()
            ->putJson("/api/admin/languages/{$language->id}", [
                'name' => 'Japanese Updated',
                'native_name' => 'にほんご',
                'is_active' => true
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'ja',
                    'name' => 'Japanese Updated',
                    'native_name' => 'にほんご',
                    'is_active' => true
                ]
            ]);

        $this->assertDatabaseHas('languages', [
            'id' => $language->id,
            'code' => 'ja',
            'name' => 'Japanese Updated',
            'native_name' => 'にほんご'
        ]);
    }

    public function test_admin_can_toggle_language_status()
    {
        $language = Language::create([
            'code' => 'ja',
            'name' => 'Japanese',
            'native_name' => '日本語',
            'is_active' => true
        ]);

        $response = $this->actingAsAdmin()
            ->patchJson("/api/admin/languages/{$language->id}/status", [
                'is_active' => false
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_active' => false
                ]
            ]);

        $this->assertDatabaseHas('languages', [
            'id' => $language->id,
            'is_active' => false
        ]);
    }

    public function test_admin_can_create_language_pair()
    {
        $sourceLanguage = Language::create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English'
        ]);

        $targetLanguage = Language::create([
            'code' => 'ja',
            'name' => 'Japanese',
            'native_name' => '日本語'
        ]);

        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/languages/pairs', [
                'source_language_id' => $sourceLanguage->id,
                'target_language_id' => $targetLanguage->id
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'source_language_id' => $sourceLanguage->id,
                    'target_language_id' => $targetLanguage->id,
                    'is_active' => true
                ]
            ]);

        $this->assertDatabaseHas('language_pairs', [
            'source_language_id' => $sourceLanguage->id,
            'target_language_id' => $targetLanguage->id,
            'is_active' => true
        ]);
    }

    public function test_admin_cannot_create_duplicate_language_pair()
    {
        $sourceLanguage = Language::create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English'
        ]);

        $targetLanguage = Language::create([
            'code' => 'ja',
            'name' => 'Japanese',
            'native_name' => '日本語'
        ]);

        // Create the pair first time
        $this->actingAsAdmin()
            ->postJson('/api/admin/languages/pairs', [
                'source_language_id' => $sourceLanguage->id,
                'target_language_id' => $targetLanguage->id
            ]);

        // Try to create the same pair again
        $response = $this->actingAsAdmin()
            ->postJson('/api/admin/languages/pairs', [
                'source_language_id' => $sourceLanguage->id,
                'target_language_id' => $targetLanguage->id
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_toggle_language_pair_status()
    {
        $sourceLanguage = Language::create([
            'code' => 'en',
            'name' => 'English',
            'native_name' => 'English'
        ]);

        $targetLanguage = Language::create([
            'code' => 'ja',
            'name' => 'Japanese',
            'native_name' => '日本語'
        ]);

        $this->actingAsAdmin()
            ->postJson('/api/admin/languages/pairs', [
                'source_language_id' => $sourceLanguage->id,
                'target_language_id' => $targetLanguage->id
            ]);

        $response = $this->actingAsAdmin()
            ->patchJson("/api/admin/languages/pairs/{$sourceLanguage->id}/{$targetLanguage->id}/status", [
                'is_active' => false
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_active' => false
                ]
            ]);

        $this->assertDatabaseHas('language_pairs', [
            'source_language_id' => $sourceLanguage->id,
            'target_language_id' => $targetLanguage->id,
            'is_active' => false
        ]);
    }
}