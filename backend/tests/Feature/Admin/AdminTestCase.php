<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\UserStreak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;
    protected Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();
        $this->createUsers();
        $this->authenticateAdmin();
    }

    protected function createRoles(): void
    {
        $this->adminRole = Role::create([
            'name' => 'admin',
            'slug' => Str::slug('admin'),
            'description' => 'Administrator'
        ]);
    }

    protected function createUsers(): void
    {
        // Create admin user with streak record
        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com'
        ]);
        $this->admin->roles()->attach($this->adminRole);
        UserStreak::factory()->create(['user_id' => $this->admin->id]);

        // Create normal user with streak record
        $this->user = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user@example.com'
        ]);
        UserStreak::factory()->create(['user_id' => $this->user->id]);
    }

    protected function authenticateAdmin(): void
    {
        Auth::login($this->admin);
    }

    protected function actingAsAdmin()
    {
        Auth::login($this->admin);
        return $this->actingAs($this->admin);
    }

    protected function actingAsUser()
    {
        Auth::login($this->user);
        return $this->actingAs($this->user);
    }

    protected function assertUnauthorized($response)
    {
        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Unauthorized. Requires role: admin'
            ]);
    }

    protected function tearDown(): void
    {
        // Clear the authenticated user without using logout
        $this->app['auth']->forgetGuards();
        parent::tearDown();
    }
}