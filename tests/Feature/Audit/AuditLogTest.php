<?php

namespace Tests\Feature\Audit;

use App\Models\ActivityPlan;
use App\Models\ActivityType;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Planning\Domain\Enums\PlanStatus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Permission::findOrCreate('master_data.manage');
        Permission::findOrCreate('users.manage');
        Role::findOrCreate('ADMIN')->givePermissionTo(['master_data.manage', 'users.manage']);

        $user = User::factory()->create();
        $user->assignRole('ADMIN');

        return $user;
    }

    public function test_creating_master_data_is_audit_logged(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/master/activity-types', [
            'name' => 'Penyuluhan',
            'code' => 'PENYULUHAN',
        ]);

        $id = $response->json('data.id');
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'created',
            'entity_type' => ActivityType::class,
            'entity_id' => $id,
        ]);
    }

    public function test_updating_master_data_logs_old_and_new_values(): void
    {
        $admin = $this->admin();
        $activityType = ActivityType::factory()->create(['name' => 'Penyuluhan', 'code' => 'PENYULUHAN']);

        $this->actingAs($admin, 'sanctum')->putJson("/api/v1/master/activity-types/{$activityType->id}", [
            'name' => 'Penyuluhan Lapangan',
            'code' => 'PENYULUHAN',
        ])->assertOk();

        $log = AuditLog::where('entity_id', $activityType->id)->where('action', 'updated')->firstOrFail();
        $this->assertSame('Penyuluhan', $log->old_values['name']);
        $this->assertSame('Penyuluhan Lapangan', $log->new_values['name']);
    }

    public function test_deleting_master_data_is_audit_logged(): void
    {
        $admin = $this->admin();
        $activityType = ActivityType::factory()->create();

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/master/activity-types/{$activityType->id}")->assertStatus(204);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'entity_type' => ActivityType::class,
            'entity_id' => $activityType->id,
        ]);
    }

    public function test_updating_a_plans_status_is_audit_logged(): void
    {
        Permission::findOrCreate('plans.create');
        Role::findOrCreate('AGRONOMIST')->givePermissionTo('plans.create');
        $agronomist = User::factory()->create();
        $agronomist->assignRole('AGRONOMIST');
        $plan = ActivityPlan::factory()->for($agronomist, 'creator')->create(['status' => PlanStatus::Planned]);

        $this->actingAs($agronomist, 'sanctum')->putJson("/api/v1/plans/{$plan->id}", ['status' => 'READY'])->assertOk();

        $log = AuditLog::where('entity_id', $plan->id)->where('action', 'updated')->firstOrFail();
        $this->assertSame('PLANNED', $log->old_values['status']);
        $this->assertSame('READY', $log->new_values['status']);
    }

    public function test_an_admin_can_revoke_a_device_and_it_is_audit_logged(): void
    {
        $admin = $this->admin();
        $device = Device::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/devices/{$device->id}/revoke")->assertOk();

        $this->assertNotNull($device->fresh()->revoked_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'revoked',
            'entity_type' => Device::class,
            'entity_id' => $device->id,
        ]);
    }

    public function test_a_revoked_device_can_no_longer_log_in_with(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $device = Device::factory()->for($user)->create();

        $this->actingAs($admin, 'sanctum')->postJson("/api/v1/admin/devices/{$device->id}/revoke")->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'device_uuid' => $device->device_uuid,
        ])->assertStatus(403)->assertJson(['code' => 'DEVICE_REVOKED']);
    }

    public function test_a_user_without_users_manage_cannot_revoke_a_device(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/admin/devices/{$device->id}/revoke")
            ->assertStatus(403);
    }
}
