<?php

namespace Tests\Feature\Activities;

use App\Models\Activity;
use App\Models\ActivityPlan;
use App\Models\ActivityType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Planning\Domain\Enums\PlanStatus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    private function agronomist(): User
    {
        Permission::findOrCreate('plans.create');
        Permission::findOrCreate('activities.create');
        Role::findOrCreate('AGRONOMIST')->givePermissionTo(['plans.create', 'activities.create']);

        $user = User::factory()->create();
        $user->assignRole('AGRONOMIST');

        return $user;
    }

    private function admin(): User
    {
        Permission::findOrCreate('activities.view');
        Role::findOrCreate('ADMIN')->givePermissionTo('activities.view');

        $user = User::factory()->create();
        $user->assignRole('ADMIN');

        return $user;
    }

    public function test_a_user_without_activities_permissions_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/activities')->assertStatus(403);
    }

    public function test_an_agronomist_can_realize_an_activity_manually(): void
    {
        $agronomist = $this->agronomist();
        $activityType = ActivityType::factory()->create();
        $products = Product::factory()->count(2)->create();

        $response = $this->actingAs($agronomist, 'sanctum')->postJson('/api/v1/activities', [
            'idempotency_key' => (string) Str::uuid(),
            'activity_type_id' => $activityType->id,
            'location' => 'Kios Tani Jaya',
            'notes' => 'Realisasi tanpa rencana',
            'product_ids' => $products->pluck('id')->all(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.activity_plan_id', null)
            ->assertJsonCount(2, 'data.products');
    }

    public function test_retrying_an_activity_creation_with_the_same_idempotency_key_does_not_duplicate_it(): void
    {
        $agronomist = $this->agronomist();
        $activityType = ActivityType::factory()->create();
        $product = Product::factory()->create();
        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'activity_type_id' => $activityType->id,
            'location' => 'Kios Tani Jaya',
            'product_ids' => [$product->id],
        ];

        $first = $this->actingAs($agronomist, 'sanctum')->postJson('/api/v1/activities', $payload);
        $first->assertStatus(201);

        $retry = $this->actingAs($agronomist, 'sanctum')->postJson('/api/v1/activities', $payload);
        $retry->assertStatus(200)->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, Activity::count());
    }

    public function test_manual_realization_requires_activity_type_and_products(): void
    {
        $agronomist = $this->agronomist();

        $this->actingAs($agronomist, 'sanctum')->postJson('/api/v1/activities', [
            'idempotency_key' => (string) Str::uuid(),
            'location' => 'Kios Tani Jaya',
        ])->assertStatus(422);
    }

    public function test_an_agronomist_can_realize_their_own_plan_and_the_plan_becomes_realized(): void
    {
        $agronomist = $this->agronomist();
        $product = Product::factory()->create();
        $plan = ActivityPlan::factory()->for($agronomist, 'creator')->create(['status' => PlanStatus::Ready]);
        $plan->products()->attach($product);

        $response = $this->actingAs($agronomist, 'sanctum')->postJson('/api/v1/activities', [
            'idempotency_key' => (string) Str::uuid(),
            'activity_plan_id' => $plan->id,
            'location' => 'Lokasi aktual kunjungan',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.activity_plan_id', $plan->id)
            ->assertJsonPath('data.activity_type.id', $plan->activity_type_id)
            ->assertJsonCount(1, 'data.products');

        $plan->refresh();
        $this->assertSame(PlanStatus::Realized, $plan->status);
        $this->assertSame($response->json('data.id'), $plan->realized_activity_id);
    }

    public function test_cannot_realize_someone_elses_plan(): void
    {
        $agronomist = $this->agronomist();
        $othersPlan = ActivityPlan::factory()->create(['status' => PlanStatus::Ready]);

        $this->actingAs($agronomist, 'sanctum')->postJson('/api/v1/activities', [
            'idempotency_key' => (string) Str::uuid(),
            'activity_plan_id' => $othersPlan->id,
            'location' => 'Somewhere',
        ])->assertStatus(422);
    }

    public function test_cannot_realize_an_already_cancelled_plan(): void
    {
        $agronomist = $this->agronomist();
        $plan = ActivityPlan::factory()->for($agronomist, 'creator')->create(['status' => PlanStatus::Cancelled]);

        $this->actingAs($agronomist, 'sanctum')->postJson('/api/v1/activities', [
            'idempotency_key' => (string) Str::uuid(),
            'activity_plan_id' => $plan->id,
            'location' => 'Somewhere',
        ])->assertStatus(422);
    }

    public function test_an_agronomist_only_sees_their_own_activities_in_the_index(): void
    {
        $agronomist = $this->agronomist();
        Activity::factory()->for($agronomist, 'creator')->create();
        Activity::factory()->create();

        $response = $this->actingAs($agronomist, 'sanctum')->getJson('/api/v1/activities');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_admin_sees_every_activity_in_the_index(): void
    {
        $admin = $this->admin();
        Activity::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/activities');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_a_user_cannot_view_another_users_activity_without_activities_view(): void
    {
        $agronomist = $this->agronomist();
        $othersActivity = Activity::factory()->create();

        $this->actingAs($agronomist, 'sanctum')
            ->getJson("/api/v1/activities/{$othersActivity->id}")
            ->assertStatus(404);
    }
}
