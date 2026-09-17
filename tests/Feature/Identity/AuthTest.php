<?php

namespace Tests\Feature\Identity;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'agronomist@ama.test',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJsonStructure([
            'user' => ['id', 'email'],
            'access_token',
            'access_token_expires_at',
            'refresh_token',
            'refresh_token_expires_at',
        ]);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_is_blocked_for_a_revoked_device(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $device = Device::factory()->for($user)->create(['revoked_at' => now()]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'device_uuid' => $device->device_uuid,
        ]);

        $response->assertStatus(403)->assertJson(['code' => 'DEVICE_REVOKED']);
    }

    public function test_refresh_token_issues_a_new_token_pair_and_invalidates_the_old_one(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->json();

        $refreshed = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login['refresh_token'],
        ]);

        $refreshed->assertOk();
        $this->assertNotSame($login['refresh_token'], $refreshed->json('refresh_token'));

        // The old refresh token was single-use and is now revoked.
        $reuse = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $login['refresh_token'],
        ]);
        $reuse->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_profile_via_me_endpoint(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        Role::findOrCreate('AGRONOMIST');
        $user->assignRole('AGRONOMIST');

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->json();

        $response = $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->getJson('/api/v1/me');

        $response->assertOk()->assertJsonPath('email', $user->email)
            ->assertJsonPath('roles.0', 'AGRONOMIST');
    }

    public function test_logout_revokes_the_access_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->json();

        $this->withHeader('Authorization', 'Bearer '.$login['access_token'])
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // Asserted against the database rather than a follow-up authenticated
        // request: Laravel's testing kernel reuses one container across
        // requests in the same test, so Sanctum's guard caches the resolved
        // user on the first call and a second simulated request would read
        // that cache instead of re-checking the (now deleted) token — an
        // artifact of the test harness, not how separate PHP-FPM requests
        // behave in production.
        $tokenId = explode('|', $login['access_token'])[0];
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }
}
