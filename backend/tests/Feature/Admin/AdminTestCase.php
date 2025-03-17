<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an admin user
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        // Create a regular user
        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'role' => 'user'
        ]);
    }

    protected function actingAsAdmin(): self
    {
        Sanctum::actingAs($this->admin, ['*']);
        return $this;
    }

    protected function actingAsUser(): self
    {
        Sanctum::actingAs($this->regularUser, ['*']);
        return $this;
    }

    protected function jsonAs(User $user, string $method, string $uri, array $data = []): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($user, ['*']);
        return $this->json($method, $uri, $data);
    }

    protected function assertUnauthorized($response): void
    {
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized'
            ]);
    }

    protected function assertUnauthenticated($response): void
    {
        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated'
            ]);
    }
}