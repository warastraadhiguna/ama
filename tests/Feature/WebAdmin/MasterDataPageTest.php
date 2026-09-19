<?php

namespace Tests\Feature\WebAdmin;

use App\Models\ActivityType;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MasterDataPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Permission::findOrCreate('master_data.manage');
        Role::findOrCreate('ADMIN')->givePermissionTo('master_data.manage');

        $user = User::factory()->create();
        $user->assignRole('ADMIN');

        return $user;
    }

    public function test_it_requires_master_data_manage(): void
    {
        $this->actingAs(User::factory()->create())->get('/master-data/activity-types')->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->post('/master-data/activity-types', ['name' => 'X', 'code' => 'X', 'is_active' => true])
            ->assertForbidden();
    }

    public function test_an_admin_can_view_every_resource_and_an_unknown_one_is_404(): void
    {
        $admin = $this->admin();

        foreach (['activity-types', 'product-categories', 'products', 'positions', 'work-locations'] as $resource) {
            $this->actingAs($admin)->get("/master-data/{$resource}")->assertOk();
        }

        $this->actingAs($admin)->get('/master-data/nope')->assertNotFound();
        $this->actingAs($admin)->get('/master-data')->assertRedirect('/master-data/activity-types');
    }

    public function test_an_admin_can_create_and_deactivate_and_it_is_audited(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/master-data/activity-types', ['name' => 'Panen Raya', 'code' => 'PANEN', 'is_active' => true])
            ->assertRedirect();

        $type = ActivityType::where('code', 'PANEN')->firstOrFail();
        $this->assertTrue($type->is_active);

        $this->actingAs($admin)
            ->put("/master-data/activity-types/{$type->id}", ['name' => 'Panen Raya', 'code' => 'PANEN', 'is_active' => false])
            ->assertRedirect();

        $this->assertFalse($type->fresh()->is_active);
        $this->assertSame(
            ['created', 'updated'],
            AuditLog::where('entity_type', ActivityType::class)->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_code_must_be_unique_but_can_stay_on_edit(): void
    {
        $admin = $this->admin();
        $existing = ActivityType::factory()->create(['code' => 'DUP']);
        $other = ActivityType::factory()->create(['code' => 'OTHER']);

        $this->actingAs($admin)
            ->put("/master-data/activity-types/{$other->id}", ['name' => 'n', 'code' => 'DUP', 'is_active' => true])
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)
            ->put("/master-data/activity-types/{$existing->id}", ['name' => 'Renamed', 'code' => 'DUP', 'is_active' => true])
            ->assertSessionHasNoErrors();
    }

    public function test_a_product_needs_an_existing_category(): void
    {
        $admin = $this->admin();
        $category = ProductCategory::factory()->create();

        $this->actingAs($admin)
            ->post('/master-data/products', ['name' => 'P', 'code' => 'P1', 'is_active' => true, 'product_category_id' => 999999])
            ->assertSessionHasErrors('product_category_id');

        $this->actingAs($admin)
            ->post('/master-data/products', ['name' => 'P', 'code' => 'P1', 'is_active' => true, 'product_category_id' => $category->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($category->id, Product::where('code', 'P1')->firstOrFail()->product_category_id);
    }
}
