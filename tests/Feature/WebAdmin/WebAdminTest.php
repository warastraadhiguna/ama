<?php

namespace Tests\Feature\WebAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WebAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Permission::findOrCreate('activities.view');
        Role::findOrCreate('ADMIN')->givePermissionTo('activities.view');

        $user = User::factory()->create();
        $user->assignRole('ADMIN');

        return $user;
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_a_user_can_log_in_and_reach_the_dashboard(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'secret123']);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_the_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_user_without_activities_view_is_forbidden_from_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertStatus(403);
    }

    public function test_an_admin_can_view_the_dashboard_and_activity_monitoring(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get('/activities')->assertOk();
    }

    public function test_logout_clears_the_session(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
