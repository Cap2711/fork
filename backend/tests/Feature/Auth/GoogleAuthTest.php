<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;
use App\Models\User;
use Mockery;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\GoogleProvider;

/**
 * Integration tests for Google Authentication
 * 
 * Note: These tests mock the Google OAuth flow for integration testing purposes.
 * For complete authentication validation:
 * 1. End-to-end tests should be implemented in a staging environment with real Google credentials
 * 2. Production environment should implement additional security measures:
 *    - Verify email domains if restricting to specific organizations
 *    - Validate email verification status
 *    - Implement rate limiting
 *    - Monitor for suspicious login patterns
 */
class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    private function createGoogleUser(string $email = 'test@example.com', string $id = '123456789'): SocialiteUser
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive([
            'getId' => $id,
            'getEmail' => $email,
            'getName' => 'Test User',
            'getAvatar' => 'https://example.com/avatar.jpg'
        ]);
        return $googleUser;
    }

    private function mockGoogleProvider(SocialiteUser $googleUser): void
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')
            ->once()
            ->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($provider);
    }

    public function test_redirect_to_google()
    {
        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn($provider);
        $provider->shouldReceive('getTargetUrl')
            ->once()
            ->andReturn('https://accounts.google.com/oauth');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($provider);

        $response = $this->getJson('/api/auth/google');
        
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'url' => 'https://accounts.google.com/oauth'
                ]
            ]);
    }

    public function test_google_callback_with_unauthorized_domain()
    {
        $googleUser = $this->createGoogleUser('test@unauthorized.com', '123456789');
        $this->mockGoogleProvider($googleUser);

        $response = $this->getJson('/api/auth/google/callback?code=test-code');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized email domain',
                'errors' => [
                    'email' => 'This email domain is not authorized to access the application.'
                ]
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'test@unauthorized.com'
        ]);
    }

    public function test_google_callback_creates_new_user()
    {
        $googleUser = $this->createGoogleUser('test@example.com', '123456789');
        $this->mockGoogleProvider($googleUser);

        $response = $this->getJson('/api/auth/google/callback?code=test-code');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Test User',
                        'email' => 'test@example.com',
                        'avatar' => 'https://example.com/avatar.jpg'
                    ]
                ]
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'avatar',
                        'role',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'message'
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
            'avatar' => 'https://example.com/old-avatar.jpg',
            'password' => bcrypt('password123'),
            'role' => 'user'
        ]);

        $googleUser = $this->createGoogleUser('test@example.com', '123456789');
        $this->mockGoogleProvider($googleUser);

        $response = $this->getJson('/api/auth/google/callback?code=test-code');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $existingUser->id,
                        'email' => 'test@example.com'
                    ]
                ]
            ]);
        
        $this->assertDatabaseHas('users', [
            'id' => $existingUser->id,
            'avatar' => 'https://example.com/avatar.jpg'
        ]);
    }

    public function test_google_callback_links_existing_email_user()
    {
        $existingUser = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'user'
        ]);

        $googleUser = $this->createGoogleUser('test@example.com', '123456789');
        $this->mockGoogleProvider($googleUser);

        $response = $this->getJson('/api/auth/google/callback?code=test-code');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $existingUser->id
                    ]
                ]
            ]);
        
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