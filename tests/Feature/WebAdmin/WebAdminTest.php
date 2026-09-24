<?php

namespace Tests\Feature\WebAdmin;

use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Integrity\Domain\Enums\IntegrityStatus;
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

    public function test_the_login_page_shows_the_configured_company_name_or_none(): void
    {
        config(['app.company_name' => null]);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('company', null));

        config(['app.company_name' => 'PT Contoh']);
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('company', 'PT Contoh'));
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

    public function test_dashboard_filters_narrow_every_card_and_default_to_today(): void
    {
        $admin = $this->admin();
        $mine = User::factory()->create();
        $other = User::factory()->create();

        Activity::factory()->for($mine, 'creator')->create();
        Activity::factory()->for($other, 'creator')->count(2)->create();
        ActivityLocation::factory()
            ->for(Activity::factory()->for($mine, 'creator'), 'activity')
            ->create(['integrity_status' => IntegrityStatus::Suspicious]);
        ActivityLocation::factory()
            ->for(Activity::factory()->for($other, 'creator'), 'activity')
            ->create(['integrity_status' => IntegrityStatus::Suspicious]);

        $this->actingAs($admin)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('isToday', true)
                ->where('summary.activities_today', 7) // 3 direct + 2 for the locations + 2 their capture-session factories add
                ->where('summary.location_alerts', 2));

        $this->actingAs($admin)->get("/dashboard?creator_id={$mine->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.activities_today', 2)
                ->where('summary.location_alerts', 1));

        $this->actingAs($admin)->get('/dashboard?date=2000-01-01')
            ->assertInertia(fn (Assert $page) => $page
                ->where('isToday', false)
                ->where('day', '2000-01-01')
                ->where('summary.activities_today', 0)
                ->where('summary.location_alerts', 0));
    }

    public function test_logout_clears_the_session(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
