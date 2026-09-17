<?php

namespace Tests\Feature\Identity;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_register_a_device(): void
    {
        $user = User::factory()->create();
        $deviceUuid = Str::uuid()->toString();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/devices/register', [
            'device_uuid' => $deviceUuid,
            'app_version' => '1.2.0',
            'os_version' => 'Android 14',
            'manufacturer' => 'Xiaomi',
            'model' => 'Redmi Note 12',
        ]);

        $response->assertOk()->assertJsonPath('device.device_uuid', $deviceUuid);
        $this->assertDatabaseHas('devices', [
            'device_uuid' => $deviceUuid,
            'user_id' => $user->id,
        ]);
    }

    public function test_re_registering_a_revoked_device_is_rejected(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->for($user)->create(['revoked_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/devices/register', [
            'device_uuid' => $device->device_uuid,
            'app_version' => '1.2.0',
        ]);

        $response->assertStatus(403)->assertJson(['code' => 'DEVICE_REVOKED']);
    }

    public function test_a_device_uuid_cannot_be_registered_under_a_different_account(): void
    {
        $owner = User::factory()->create();
        $device = Device::factory()->for($owner)->create();

        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser, 'sanctum')->postJson('/api/v1/devices/register', [
            'device_uuid' => $device->device_uuid,
            'app_version' => '1.2.0',
        ]);

        $response->assertStatus(403)->assertJson(['code' => 'DEVICE_REVOKED']);
    }
}
