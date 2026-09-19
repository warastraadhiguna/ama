<?php

namespace Tests\Feature\Reports;

use App\Models\Activity;
use App\Models\ActivityPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Planning\Domain\Enums\PlanStatus;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Permission::findOrCreate('reports.view');
        Role::findOrCreate('ADMIN')->givePermissionTo('reports.view');

        $user = User::factory()->create();
        $user->assignRole('ADMIN');

        return $user;
    }

    public function test_a_user_without_reports_view_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/reports/activities/summary')->assertStatus(403);
    }

    public function test_the_summary_reports_realization_rate_excluding_cancelled_plans(): void
    {
        $admin = $this->admin();
        ActivityPlan::factory()->count(2)->create(['status' => PlanStatus::Realized]);
        ActivityPlan::factory()->create(['status' => PlanStatus::Planned]);
        ActivityPlan::factory()->create(['status' => PlanStatus::Cancelled]);
        Activity::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/reports/activities/summary');

        $response->assertOk()
            ->assertJsonPath('data.activities_total', 3)
            ->assertJsonPath('data.plans_total', 4)
            ->assertJsonPath('data.plans_realized', 2)
            ->assertJsonPath('data.plans_cancelled', 1)
            // 2 realized out of 3 eligible (4 total - 1 cancelled).
            ->assertJsonPath('data.realization_rate', 0.6667);
    }

    public function test_the_summary_can_be_filtered_by_creator(): void
    {
        $admin = $this->admin();
        $agronomist = User::factory()->create();
        Activity::factory()->for($agronomist, 'creator')->create();
        Activity::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/v1/reports/activities/summary?creator_id={$agronomist->id}");

        $response->assertOk()->assertJsonPath('data.activities_total', 1);
    }

    public function test_csv_neutralizes_formula_injection_from_free_text(): void
    {
        $admin = $this->admin();
        Activity::factory()->create(['location' => '=HYPERLINK("http://evil.test","x")']);

        $csv = $this->actingAs($admin, 'sanctum')->get('/api/v1/reports/activities/export')->getContent();

        $this->assertStringContainsString('"\'=HYPERLINK(', $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK(', $csv);
    }

    public function test_the_web_report_page_and_csv_need_reports_view(): void
    {
        $admin = $this->admin();
        Activity::factory()->create();

        $this->actingAs(User::factory()->create())->get('/reports')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/reports/export')->assertForbidden();

        $this->actingAs($admin)->get('/reports?date_from=2000-01-01')->assertOk();
        $this->actingAs($admin)->get('/reports/export')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="activities-report.csv"');
    }

    public function test_export_returns_a_csv_file(): void
    {
        $admin = $this->admin();
        Activity::factory()->create();

        $response = $this->actingAs($admin, 'sanctum')->get('/api/v1/reports/activities/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('id,date,creator', $response->getContent());
    }
}
