<?php

namespace Tests\Feature\Notifications;

use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Models\ActivityPhoto;
use App\Models\ActivityPlan;
use App\Models\CaptureSession;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Integrity\Domain\Enums\IntegrityStatus;
use Modules\Notifications\Application\UseCases\SendPlanRemindersUseCase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_only_sees_their_own_notifications(): void
    {
        $user = User::factory()->create();
        Notification::factory()->for($user)->create();
        Notification::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/notifications');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_marking_a_notification_read_sets_read_at(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->for($user)->create();

        $response = $this->actingAs($user, 'sanctum')->postJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_a_user_cannot_mark_someone_elses_notification_as_read(): void
    {
        $user = User::factory()->create();
        $othersNotification = Notification::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/notifications/{$othersNotification->id}/read")
            ->assertStatus(404);
    }

    public function test_completing_an_activity_notifies_the_creator(): void
    {
        Permission::findOrCreate('activities.create');
        Role::findOrCreate('AGRONOMIST')->givePermissionTo('activities.create');
        $agronomist = User::factory()->create();
        $agronomist->assignRole('AGRONOMIST');

        $activity = Activity::factory()->for($agronomist, 'creator')->create();
        $session = CaptureSession::factory()->for($activity)->create();
        ActivityLocation::factory()->for($activity)->for($session, 'captureSession')->create([
            'integrity_status' => IntegrityStatus::Trusted,
        ]);
        ActivityPhoto::factory()->for($activity)->for($session, 'captureSession')->create();

        $this->actingAs($agronomist, 'sanctum')->postJson("/api/v1/activities/{$activity->id}/complete")->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $agronomist->id,
            'type' => 'ACTIVITY_COMPLETED',
        ]);
    }

    public function test_completing_an_activity_with_a_suspicious_location_notifies_reviewers(): void
    {
        Permission::findOrCreate('activities.create');
        Permission::findOrCreate('activities.verify');
        Role::findOrCreate('AGRONOMIST')->givePermissionTo('activities.create');
        Role::findOrCreate('SUPERVISOR')->givePermissionTo('activities.verify');

        $agronomist = User::factory()->create();
        $agronomist->assignRole('AGRONOMIST');
        $supervisor = User::factory()->create();
        $supervisor->assignRole('SUPERVISOR');

        $activity = Activity::factory()->for($agronomist, 'creator')->create();
        $session = CaptureSession::factory()->for($activity)->create();
        ActivityLocation::factory()->for($activity)->for($session, 'captureSession')->create([
            'integrity_status' => IntegrityStatus::Suspicious,
        ]);
        ActivityPhoto::factory()->for($activity)->for($session, 'captureSession')->create();

        $this->actingAs($agronomist, 'sanctum')->postJson("/api/v1/activities/{$activity->id}/complete")->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $supervisor->id,
            'type' => 'ACTIVITY_NEEDS_REVIEW',
        ]);
    }

    public function test_plan_reminders_are_sent_for_tomorrow_today_and_overdue_and_not_duplicated_on_rerun(): void
    {
        $agronomist = User::factory()->create();
        $planTomorrow = ActivityPlan::factory()->for($agronomist, 'creator')->create([
            'planned_date' => now()->addDay(),
        ]);
        $planToday = ActivityPlan::factory()->for($agronomist, 'creator')->create([
            'planned_date' => now(),
        ]);
        $planOverdue = ActivityPlan::factory()->for($agronomist, 'creator')->create([
            'planned_date' => now()->subDays(2),
        ]);

        $useCase = app(SendPlanRemindersUseCase::class);
        $first = $useCase->handle();

        $this->assertSame(['tomorrow' => 1, 'today' => 1, 'overdue' => 1], $first);
        $this->assertDatabaseHas('notifications', ['type' => 'PLAN_TOMORROW']);
        $this->assertDatabaseHas('notifications', ['type' => 'PLAN_TODAY']);
        $this->assertDatabaseHas('notifications', ['type' => 'PLAN_OVERDUE']);

        // Re-running must not spam the same reminders again.
        $second = $useCase->handle();
        $this->assertSame(['tomorrow' => 0, 'today' => 0, 'overdue' => 0], $second);
    }

    public function test_device_registration_stores_the_fcm_token(): void
    {
        Permission::findOrCreate('activities.create');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/devices/register', [
            'device_uuid' => (string) Str::uuid(),
            'app_version' => '1.0.0',
            'fcm_token' => 'fake-fcm-token',
        ])->assertOk();

        $this->assertDatabaseHas('devices', ['user_id' => $user->id, 'fcm_token' => 'fake-fcm-token']);
    }
}
