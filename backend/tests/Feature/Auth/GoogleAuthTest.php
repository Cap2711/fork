<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_to_google()
    {
        $response = $this->getJson('/api/auth/google');
        
        $response->assertStatus(200)
            ->assertJsonStructure(['url']);
    }

    public function test_google_callback_creates_new_user()
    {
        $socialiteUser = $this->createMock(SocialiteUser::class);
        $socialiteUser->method('getId')->willReturn('123456789');
        $socialiteUser->method('getEmail')->willReturn('test@example.com');
        $socialiteUser->method('getName')->willReturn('Test User');
        $socialiteUser->method('getAvatar')->willReturn('https://example.com/avatar.jpg');

        Socialite::shouldReceive('driver->user')
            ->once()
            ->andReturn($socialiteUser);

        $response = $this->get('/api/auth/google/callback?code=test-code');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'google_id',
                    'avatar'
                ],
                'token'
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'google_id' => '123456789',
            'name' => 'Test User',
            'avatar' => 'https://example.com/avatar.jpg'
        ]);
    }

    public function test_google_callback_logs_in_existing_user()
    {
        $existingUser = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'google_id' => '123456789',
            'avatar' => 'https://example.com/old-avatar.jpg'
        ]);

        $socialiteUser = $this->createMock(SocialiteUser::class);
        $socialiteUser->method('getId')->willReturn('123456789');
        $socialiteUser->method('getEmail')->willReturn('test@example.com');
        $socialiteUser->method('getName')->willReturn('Test User');
        $socialiteUser->method('getAvatar')->willReturn('https://example.com/new-avatar.jpg');

        Socialite::shouldReceive('driver->user')
            ->once()
            ->andReturn($socialiteUser);

        $response = $this->get('/api/auth/google/callback?code=test-code');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'google_id',
                    'avatar'
                ],
                'token'
            ]);

        $this->assertEquals($existingUser->id, $response->json('user.id'));
        
        // Verify avatar is updated
        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'avatar' => 'https://example.com/new-avatar.jpg'
        ]);
    }

    public function test_google_callback_links_existing_email_user()
    {
        $existingUser = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        $socialiteUser = $this->createMock(SocialiteUser::class);
        $socialiteUser->method('getId')->willReturn('123456789');
        $socialiteUser->method('getEmail')->willReturn('test@example.com');
        $socialiteUser->method('getName')->willReturn('Test User');
        $socialiteUser->method('getAvatar')->willReturn('https://example.com/avatar.jpg');

        Socialite::shouldReceive('driver->user')
            ->once()
            ->andReturn($socialiteUser);

        $response = $this->get('/api/auth/google/callback?code=test-code');

        $response->assertStatus(200);
        
        // Verify Google ID was linked to existing account
        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'email' => 'test@example.com',
            'google_id' => '123456789',
            'avatar' => 'https://example.com/avatar.jpg'
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }
}