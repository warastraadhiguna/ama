<?php

namespace Tests\Feature\MasterData;

use App\Models\ActivityType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        Permission::findOrCreate('master_data.manage');
        $role = Role::findOrCreate('ADMIN');
        $role->givePermissionTo('master_data.manage');

        $admin = User::factory()->create();
        $admin->assignRole('ADMIN');

        return $admin;
    }

    public function test_guest_cannot_list_master_data(): void
    {
        $this->getJson('/api/v1/master/activity-types')->assertStatus(401);
    }

    public function test_any_authenticated_user_can_list_activity_types(): void
    {
        ActivityType::factory()->create(['name' => 'Penyuluhan', 'code' => 'PENYULUHAN']);
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/master/activity-types');

        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_user_without_permission_cannot_create_an_activity_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/master/activity-types', [
            'name' => 'Penyuluhan',
            'code' => 'PENYULUHAN',
        ]);

        $response->assertStatus(403);
    }

    public function test_an_admin_with_permission_can_manage_activity_types(): void
    {
        $admin = $this->adminUser();

        $created = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/master/activity-types', [
            'name' => 'Penyuluhan',
            'code' => 'PENYULUHAN',
        ])->assertStatus(201)->json('data');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/master/activity-types/{$created['id']}", [
                'name' => 'Penyuluhan Lapangan',
                'code' => 'PENYULUHAN',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Penyuluhan Lapangan')
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/master/activity-types/{$created['id']}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('activity_types', ['id' => $created['id']]);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        $admin = $this->adminUser();
        ActivityType::factory()->create(['code' => 'PENYULUHAN']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/master/activity-types', [
            'name' => 'Penyuluhan Lain',
            'code' => 'PENYULUHAN',
        ])->assertStatus(422);
    }

    public function test_products_can_be_filtered_by_category_for_the_dependent_dropdown(): void
    {
        $decomposer = ProductCategory::factory()->create(['name' => 'Decomposer', 'code' => 'DECOMPOSER']);
        $humat = ProductCategory::factory()->create(['name' => 'Senyawa Humat', 'code' => 'HUMAT']);
        Product::factory()->for($decomposer, 'category')->create(['name' => 'BEKA', 'code' => 'BEKA']);
        Product::factory()->for($humat, 'category')->create(['name' => 'POMMIX', 'code' => 'POMMIX']);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/master/products?product_category_id={$decomposer->id}");

        $response->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'BEKA');
    }

    public function test_creating_a_product_requires_a_valid_category(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/master/products', [
            'name' => 'BEKA',
            'code' => 'BEKA',
            'product_category_id' => 999,
        ])->assertStatus(422);
    }
}
