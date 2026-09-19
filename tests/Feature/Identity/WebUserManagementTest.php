<?php

namespace Tests\Feature\Identity;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('users.manage');
        Role::findOrCreate('ADMIN')->givePermissionTo('users.manage');
        Role::findOrCreate('SUPER_ADMIN')->givePermissionTo('users.manage');
        Role::findOrCreate('AGRONOMIST');
    }

    private function actor(string $role = 'ADMIN'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi Lapangan',
            'email' => 'budi@example.test',
            'role' => 'AGRONOMIST',
            'is_active' => true,
            'password' => 'password123',
        ], $overrides);
    }

    public function test_a_user_without_users_manage_is_forbidden(): void
    {
        $agronomist = $this->actor('AGRONOMIST');

        $this->actingAs($agronomist)->get('/users')->assertForbidden();
        $this->actingAs($agronomist)->post('/users', $this->payload())->assertForbidden();
    }

    public function test_an_admin_can_list_and_create_a_user_and_the_audit_row_has_no_password(): void
    {
        $admin = $this->actor();

        $this->actingAs($admin)->get('/users')->assertOk();

        $this->actingAs($admin)->post('/users', $this->payload())->assertRedirect('/users');

        $created = User::where('email', 'budi@example.test')->firstOrFail();
        $this->assertTrue($created->hasRole('AGRONOMIST'));
        $this->assertTrue($created->is_active);
        $this->assertNotSame('password123', $created->password);

        $audit = AuditLog::where('entity_type', User::class)->where('entity_id', $created->id)->firstOrFail();
        $this->assertSame('created', $audit->action);
        $this->assertArrayNotHasKey('password', $audit->new_values);
    }

    public function test_an_admin_cannot_assign_the_super_admin_role(): void
    {
        $admin = $this->actor();

        $this->actingAs($admin)
            ->post('/users', $this->payload(['role' => 'SUPER_ADMIN']))
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'budi@example.test']);
    }

    public function test_an_admin_cannot_edit_a_super_admin(): void
    {
        $admin = $this->actor();
        $super = $this->actor('SUPER_ADMIN');

        $this->actingAs($admin)
            ->put("/users/{$super->id}", $this->payload(['email' => $super->email, 'role' => 'AGRONOMIST']))
            ->assertForbidden();

        $this->assertTrue($super->fresh()->hasRole('SUPER_ADMIN'));
    }

    public function test_email_must_be_unique_but_may_stay_the_same_on_edit(): void
    {
        $admin = $this->actor();
        $existing = User::factory()->create(['email' => 'taken@example.test']);
        $existing->assignRole('AGRONOMIST');

        $this->actingAs($admin)
            ->post('/users', $this->payload(['email' => 'taken@example.test']))
            ->assertSessionHasErrors('email');

        $this->actingAs($admin)
            ->put("/users/{$existing->id}", $this->payload(['email' => 'taken@example.test', 'password' => '']))
            ->assertRedirect('/users');
    }

    public function test_editing_without_a_password_keeps_the_old_one(): void
    {
        $admin = $this->actor();
        $user = User::factory()->create(['password' => 'old-password-1']);
        $user->assignRole('AGRONOMIST');
        $oldHash = $user->password;

        $this->actingAs($admin)
            ->put("/users/{$user->id}", $this->payload(['email' => $user->email, 'name' => 'Renamed', 'password' => '']))
            ->assertRedirect('/users');

        $this->assertSame('Renamed', $user->fresh()->name);
        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_deactivating_a_user_revokes_their_api_and_refresh_tokens(): void
    {
        $admin = $this->actor();
        $user = User::factory()->create();
        $user->assignRole('AGRONOMIST');
        $user->createToken('mobile');
        $refresh = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'x'),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->put("/users/{$user->id}", $this->payload(['email' => $user->email, 'is_active' => false, 'password' => '']))
            ->assertRedirect('/users');

        $this->assertFalse($user->fresh()->is_active);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotNull($refresh->fresh()->revoked_at);
    }

    public function test_an_admin_cannot_deactivate_or_re_role_themselves(): void
    {
        $admin = $this->actor();

        $this->actingAs($admin)
            ->put("/users/{$admin->id}", $this->payload(['email' => $admin->email, 'role' => 'ADMIN', 'is_active' => false, 'password' => '']))
            ->assertSessionHasErrors('is_active');

        $this->actingAs($admin)
            ->put("/users/{$admin->id}", $this->payload(['email' => $admin->email, 'role' => 'AGRONOMIST', 'password' => '']))
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->is_active);
        $this->assertTrue($admin->fresh()->hasRole('ADMIN'));
    }

    public function test_an_admin_can_revoke_a_users_device_but_only_via_that_user(): void
    {
        $admin = $this->actor();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $device = Device::factory()->create(['user_id' => $user->id]);

        $this->actingAs($admin)->post("/users/{$other->id}/devices/{$device->id}/revoke")->assertNotFound();
        $this->assertNull($device->fresh()->revoked_at);

        $this->actingAs($admin)->post("/users/{$user->id}/devices/{$device->id}/revoke")->assertRedirect();
        $this->assertNotNull($device->fresh()->revoked_at);
    }
}
