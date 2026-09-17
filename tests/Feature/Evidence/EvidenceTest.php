<?php

namespace Tests\Feature\Evidence;

use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Models\ActivityPhoto;
use App\Models\CaptureSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Activities\Domain\Enums\ActivityStatus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EvidenceTest extends TestCase
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

    /**
     * Regression test: actingAs() (used by the other tests here) fakes
     * authentication without creating a real Sanctum token, so it can't
     * catch bugs tied to the token itself. Logging in for real reproduces
     * the bug this caught: a login without a device_uuid names the token
     * "login" (not a UUID — see LoginUseCase), and ResolveCurrentDevice
     * used to pass that straight into a `where('device_uuid', ...)` query
     * against a uuid-typed column, which Postgres rejects outright.
     */
    public function test_submitting_a_location_works_after_a_real_login_without_a_device(): void
    {
        $agronomist = $this->agronomist();
        $agronomist->forceFill(['password' => bcrypt('secret123')])->save();

        $accessToken = $this->postJson('/api/v1/auth/login', [
            'email' => $agronomist->email,
            'password' => 'secret123',
        ])->json('access_token');

        $activity = Activity::factory()->for($agronomist, 'creator')->create();

        $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->postJson("/api/v1/activities/{$activity->id}/location", [
                'capture_session_uuid' => (string) Str::uuid(),
                'started_at' => now()->toIso8601String(),
                'latitude' => -7.150975,
                'longitude' => 111.880566,
                'accuracy' => 8.5,
                'captured_at_device' => now()->toIso8601String(),
            ])
            ->assertStatus(201);
    }

    public function test_the_creator_can_submit_a_location_for_their_draft_activity(): void
    {
        $agronomist = $this->agronomist();
        $activity = Activity::factory()->for($agronomist, 'creator')->create();
        $sessionUuid = (string) Str::uuid();

        $response = $this->actingAs($agronomist, 'sanctum')->postJson("/api/v1/activities/{$activity->id}/location", [
            'capture_session_uuid' => $sessionUuid,
            'started_at' => now()->toIso8601String(),
            'latitude' => -7.150975,
            'longitude' => 111.880566,
            'accuracy' => 8.5,
            'captured_at_device' => now()->toIso8601String(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('capture_sessions', [
            'activity_id' => $activity->id,
            'client_uuid' => $sessionUuid,
        ]);
    }

    public function test_a_user_cannot_submit_a_location_for_someone_elses_activity(): void
    {
        $agronomist = $this->agronomist();
        $othersActivity = Activity::factory()->create();

        $this->actingAs($agronomist, 'sanctum')->postJson("/api/v1/activities/{$othersActivity->id}/location", [
            'capture_session_uuid' => (string) Str::uuid(),
            'started_at' => now()->toIso8601String(),
            'latitude' => -7.15,
            'longitude' => 111.88,
            'accuracy' => 8.5,
            'captured_at_device' => now()->toIso8601String(),
        ])->assertStatus(403);
    }

    public function test_evidence_cannot_be_added_once_the_activity_is_no_longer_a_draft(): void
    {
        $agronomist = $this->agronomist();
        $activity = Activity::factory()->for($agronomist, 'creator')->create(['status' => ActivityStatus::Submitted]);

        $this->actingAs($agronomist, 'sanctum')->postJson("/api/v1/activities/{$activity->id}/location", [
            'capture_session_uuid' => (string) Str::uuid(),
            'started_at' => now()->toIso8601String(),
            'latitude' => -7.15,
            'longitude' => 111.88,
            'accuracy' => 8.5,
            'captured_at_device' => now()->toIso8601String(),
        ])->assertStatus(422);
    }

    public function test_the_creator_can_upload_a_photo_which_is_hashed_and_stored(): void
    {
        Storage::fake();
        $agronomist = $this->agronomist();
        $activity = Activity::factory()->for($agronomist, 'creator')->create();
        $sessionUuid = (string) Str::uuid();
        $file = UploadedFile::fake()->image('evidence.jpg', 800, 600)->size(500);

        $response = $this->actingAs($agronomist, 'sanctum')->post("/api/v1/activities/{$activity->id}/photos", [
            'photo' => $file,
            'capture_session_uuid' => $sessionUuid,
            'started_at' => now()->toIso8601String(),
            'latitude' => -7.150975,
            'longitude' => 111.880566,
            'accuracy' => 8.5,
            'captured_at_device' => now()->toIso8601String(),
        ]);

        $response->assertStatus(201)->assertJsonPath('data.integrity_status', 'PENDING');
        $this->assertDatabaseHas('activity_photos', ['activity_id' => $activity->id]);
        $storedPath = ActivityPhoto::first()->storage_path;
        Storage::assertExists($storedPath);
    }

    public function test_completing_an_activity_without_any_evidence_fails(): void
    {
        $agronomist = $this->agronomist();
        $activity = Activity::factory()->for($agronomist, 'creator')->create();

        $this->actingAs($agronomist, 'sanctum')
            ->postJson("/api/v1/activities/{$activity->id}/complete")
            ->assertStatus(422);
    }

    public function test_completing_an_activity_with_only_a_location_and_no_photo_fails(): void
    {
        $agronomist = $this->agronomist();
        $activity = Activity::factory()->for($agronomist, 'creator')->create();
        $session = CaptureSession::factory()->for($activity)->create();
        ActivityLocation::factory()->for($activity)->for($session, 'captureSession')->create();

        $this->actingAs($agronomist, 'sanctum')
            ->postJson("/api/v1/activities/{$activity->id}/complete")
            ->assertStatus(422);
    }

    public function test_completing_an_activity_with_a_full_capture_session_submits_it(): void
    {
        $agronomist = $this->agronomist();
        $activity = Activity::factory()->for($agronomist, 'creator')->create();
        $session = CaptureSession::factory()->for($activity)->create();
        ActivityLocation::factory()->for($activity)->for($session, 'captureSession')->create();
        ActivityPhoto::factory()->for($activity)->for($session, 'captureSession')->create();

        $response = $this->actingAs($agronomist, 'sanctum')->postJson("/api/v1/activities/{$activity->id}/complete");

        $response->assertOk()->assertJsonPath('data.status', 'SUBMITTED');
        $this->assertNotNull($session->fresh()->submitted_at);
    }
}
