<?php

namespace Tests\Feature\Planning;

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

class PlanTest extends TestCase
{
    use RefreshDatabase;

    private function agronomist(): User
    {
        Permission::findOrCreate('plans.create');
        Role::findOrCreate('AGRONOMIST')->givePermissionTo('plans.create');

        $user = User::factory()->create();
        $user->assignRole('AGRONOMIST');

        return $user;
    }

    private function admin(): User
    {
        Permission::findOrCreate('plans.view');
        Role::findOrCreate('ADMIN')->givePermissionTo('plans.view');

        $user = User::factory()->create();
        $user->assignRole('ADMIN');

        return $user;
    }

    public function test_a_user_without_plans_permissions_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/plans')->assertStatus(403);
    }

    public function test_an_agronomist_can_create_a_plan_with_products(): void
    {
        $agronomist = $this->agronomist();
        $activityType = ActivityType::factory()->create();
        $products = Product::factory()->count(2)->create();

        $response = $this->actingAs($agronomist, 'sanctum')->postJson('/api/v1/plans', [
            'idempotency_key' => (string) Str::uuid(),
            'activity_type_id' => $activityType->id,
            'location' => 'Toko Tani Makmur, Bojonegoro',
            'planned_date' => now()->addDays(3)->toDateString(),
            'notes' => 'Bawa brosur',
            'product_ids' => $products->pluck('id')->all(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'PLANNED')
            ->assertJsonPath('data.creator.id', $agronomist->id)
            ->assertJsonCount(2, 'data.products');
    }

    public function test_retrying_a_plan_creation_with_the_same_idempotency_key_does_not_duplicate_it(): void
    {
        $agronomist = $this->agronomist();
        $activityType = ActivityType::factory()->create();
        $product = Product::factory()->create();
        $payload = [
            'idempotency_key' => (string) Str::uuid(),
            'activity_type_id' => $activityType->id,
            'location' => 'Toko Tani Makmur, Bojonegoro',
            'planned_date' => now()->addDays(3)->toDateString(),
            'product_ids' => [$product->id],
        ];

        $first = $this->actingAs($agronomist, 'sanctum')->postJson('/api/v1/plans', $payload);
        $first->assertStatus(201);

        $retry = $this->actingAs($agronomist, 'sanctum')->postJson('/api/v1/plans', $payload);
        $retry->assertStatus(200)->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, ActivityPlan::count());
    }

    public function test_creating_a_plan_requires_at_least_one_product(): void
    {
        $agronomist = $this->agronomist();
        $activityType = ActivityType::factory()->create();

        $this->actingAs($agronomist, 'sanctum')->postJson('/api/v1/plans', [
            'idempotency_key' => (string) Str::uuid(),
            'activity_type_id' => $activityType->id,
            'location' => 'Toko Tani Makmur',
            'planned_date' => now()->addDay()->toDateString(),
            'product_ids' => [],
        ])->assertStatus(422);
    }

    public function test_an_agronomist_only_sees_their_own_plans_in_the_index(): void
    {
        $agronomist = $this->agronomist();
        $other = User::factory()->create();

        ActivityPlan::factory()->for($agronomist, 'creator')->create();
        ActivityPlan::factory()->for($other, 'creator')->create();

        $response = $this->actingAs($agronomist, 'sanctum')->getJson('/api/v1/plans');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_an_admin_sees_every_plan_in_the_index(): void
    {
        $admin = $this->admin();
        ActivityPlan::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/plans');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_a_user_cannot_view_another_users_plan_without_plans_view(): void
    {
        $agronomist = $this->agronomist();
        $othersPlan = ActivityPlan::factory()->create();

        $this->actingAs($agronomist, 'sanctum')
            ->getJson("/api/v1/plans/{$othersPlan->id}")
            ->assertStatus(404);
    }

    public function test_an_admin_can_view_but_not_edit_someone_elses_plan(): void
    {
        $admin = $this->admin();
        $plan = ActivityPlan::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/plans/{$plan->id}")
            ->assertOk();

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/plans/{$plan->id}", ['location' => 'Somewhere else'])
            ->assertStatus(403);
    }

    public function test_the_creator_can_update_their_own_plan_and_move_it_to_ready(): void
    {
        $agronomist = $this->agronomist();
        $plan = ActivityPlan::factory()->for($agronomist, 'creator')->create();

        $response = $this->actingAs($agronomist, 'sanctum')->putJson("/api/v1/plans/{$plan->id}", [
            'status' => 'READY',
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'READY');
    }

    public function test_a_plan_cannot_be_set_to_realized_through_the_api(): void
    {
        $agronomist = $this->agronomist();
        $plan = ActivityPlan::factory()->for($agronomist, 'creator')->create();

        $this->actingAs($agronomist, 'sanctum')
            ->putJson("/api/v1/plans/{$plan->id}", ['status' => 'REALIZED'])
            ->assertStatus(422);
    }

    public function test_a_cancelled_plan_can_no_longer_be_edited(): void
    {
        $agronomist = $this->agronomist();
        $plan = ActivityPlan::factory()->for($agronomist, 'creator')->create([
            'status' => PlanStatus::Cancelled,
        ]);

        $this->actingAs($agronomist, 'sanctum')
            ->putJson("/api/v1/plans/{$plan->id}", ['location' => 'New place'])
            ->assertStatus(422);
    }
}
