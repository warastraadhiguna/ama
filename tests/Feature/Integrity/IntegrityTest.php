<?php

namespace Tests\Feature\Integrity;

use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Models\ActivityPhoto;
use App\Models\CaptureSession;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function agronomist(): User
    {
        Permission::findOrCreate('activities.create');
        Role::findOrCreate('AGRONOMIST')->givePermissionTo('activities.create');

        $user = User::factory()->create();
        $user->assignRole('AGRONOMIST');

        return $user;
    }

    public function test_a_mock_location_is_flagged_suspicious_by_default_but_not_rejected(): void
    {
        $agronomist = $this->agronomist();
        $activity = Activity::factory()->for($agronomist, 'creator')->create();

        $response = $this->actingAs($agronomist, 'sanctum')->postJson("/api/v1/activities/{$activity->id}/location", [
            'capture_session_uuid' => (string) Str::uuid(),
            'started_at' => now()->toIso8601String(),
            'latitude' => -7.150975,
            'longitude' => 111.880566,
            'accuracy' => 8.5,
            'is_mock_location' => true,
            'captured_at_device' => now()->toIso8601String(),
        ]);

        $response->assertStatus(201)->assertJsonPath('data.integrity_status', 'SUSPICIOUS');
        $this->assertDatabaseHas('activity_locations', [
            'activity_id' => $activity->id,
            'is_mock_location' => true,
            'integrity_status' => 'SUSPICIOUS',
        ]);
    }

    public function test_a_mock_location_is_rejected_outright_when_policy_is_block(): void
    {
        Config::set('integrity.mock_location_policy', 'BLOCK');
        $agronomist = $this->agronomist();
        $activity = Activity::factory()->for($agronomist, 'creator')->create();

        $this->actingAs($agronomist, 'sanctum')->postJson("/api/v1/activities/{$activity->id}/location", [
            'capture_session_uuid' => (string) Str::uuid(),
            'started_at' => now()->toIso8601String(),
            'latitude' => -7.150975,
            'longitude' => 111.880566,
            'accuracy' => 8.5,
            'is_mock_location' => true,
            'captured_at_device' => now()->toIso8601String(),
        ])->assertStatus(403);

        $this->assertDatabaseMissing('activity_locations', ['activity_id' => $activity->id]);
    }

    public function test_poor_gps_accuracy_is_flagged_suspicious(): void
    {
        $agronomist = $this->agronomist();
        $activity = Activity::factory()->for($agronomist, 'creator')->create();

        $response = $this->actingAs($agronomist, 'sanctum')->postJson("/api/v1/activities/{$activity->id}/location", [
            'capture_session_uuid' => (string) Str::uuid(),
            'started_at' => now()->toIso8601String(),
            'latitude' => -7.150975,
            'longitude' => 111.880566,
            'accuracy' => 500,
            'is_mock_location' => false,
            'captured_at_device' => now()->toIso8601String(),
        ]);

        $response->assertStatus(201)->assertJsonPath('data.integrity_status', 'SUSPICIOUS');
        $this->assertDatabaseHas('activity_locations', ['activity_id' => $activity->id, 'integrity_status' => 'SUSPICIOUS']);
    }

    public function test_an_implausible_jump_between_consecutive_locations_is_flagged_as_impossible_travel(): void
    {
        $agronomist = $this->agronomist();
        $firstActivity = Activity::factory()->for($agronomist, 'creator')->create();
        $secondActivity = Activity::factory()->for($agronomist, 'creator')->create();

        // Bojonegoro, ~10 minutes ago.
        $this->actingAs($agronomist, 'sanctum')->postJson("/api/v1/activities/{$firstActivity->id}/location", [
            'capture_session_uuid' => (string) Str::uuid(),
            'started_at' => now()->subMinutes(10)->toIso8601String(),
            'latitude' => -7.150975,
            'longitude' => 111.880566,
            'accuracy' => 8.5,
            'is_mock_location' => false,
            'captured_at_device' => now()->subMinutes(10)->toIso8601String(),
        ])->assertStatus(201);

        // Jakarta, now — ~700km away in 10 minutes is not survivable by road.
        $response = $this->actingAs($agronomist, 'sanctum')->postJson("/api/v1/activities/{$secondActivity->id}/location", [
            'capture_session_uuid' => (string) Str::uuid(),
            'started_at' => now()->toIso8601String(),
            'latitude' => -6.200000,
            'longitude' => 106.816666,
            'accuracy' => 8.5,
            'is_mock_location' => false,
            'captured_at_device' => now()->toIso8601String(),
        ]);

        $response->assertStatus(201)->assertJsonPath('data.integrity_status', 'SUSPICIOUS');
        $this->assertDatabaseHas('activity_locations', [
            'activity_id' => $secondActivity->id,
            'integrity_status' => 'SUSPICIOUS',
        ]);
    }

    public function test_completing_an_activity_flags_a_capture_session_whose_photo_and_location_are_too_far_apart_in_time(): void
    {
        $agronomist = $this->agronomist();
        $activity = Activity::factory()->for($agronomist, 'creator')->create();
        $session = CaptureSession::factory()->for($activity)->create();
        $location = ActivityLocation::factory()->for($activity)->for($session, 'captureSession')->create([
            'captured_at_device' => now()->subMinutes(5),
        ]);
        ActivityPhoto::factory()->for($activity)->for($session, 'captureSession')->create([
            'captured_at_device' => now(),
        ]);

        $response = $this->actingAs($agronomist, 'sanctum')->postJson("/api/v1/activities/{$activity->id}/complete");

        // Completion itself isn't blocked by this (docs section 23: V1 must
        // not force approval) — it only annotates the location.
        $response->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $location->refresh();
        $this->assertSame('SUSPICIOUS', $location->integrity_status->value);
        $this->assertContains('CAPTURE_WINDOW_EXCEEDED', $location->anomaly_reasons);
    }

    public function test_play_integrity_endpoint_reports_itself_as_unconfigured_and_leaves_the_device_untouched(): void
    {
        $agronomist = $this->agronomist();
        $device = Device::factory()->for($agronomist, 'user')->create(['integrity_status' => 'TRUSTED']);

        $response = $this->actingAs($agronomist, 'sanctum')->postJson('/api/v1/integrity/play', [
            'device_uuid' => $device->device_uuid,
            'integrity_token' => 'some-opaque-token-from-the-android-app',
        ]);

        $response->assertOk()->assertJsonPath('configured', false)->assertJsonPath('device_integrity_status', 'TRUSTED');
    }
}
