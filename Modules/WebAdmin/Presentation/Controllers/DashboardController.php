<?php

namespace Modules\WebAdmin\Presentation\Controllers;

use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Models\ActivityPlan;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Integrity\Domain\Enums\IntegrityStatus;
use Modules\Planning\Domain\Enums\PlanStatus;

/**
 * docs section 29.1. "TODAY" is calendar-day scoped to the server's
 * timezone — the doc doesn't specify per-territory timezones and this is a
 * single-country (Indonesia) deployment, so one clock is a reasonable
 * default rather than an OPEN QUESTION worth blocking on.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = now()->startOfDay();

        return Inertia::render('Dashboard', [
            'summary' => [
                'activities_today' => Activity::whereDate('created_at', $today)->count(),
                'planned_today' => ActivityPlan::whereDate('planned_date', $today)->count(),
                'not_realized' => ActivityPlan::whereDate('planned_date', '<=', $today)
                    ->whereIn('status', [PlanStatus::Planned, PlanStatus::Ready])
                    ->count(),
                'location_alerts' => ActivityLocation::whereDate('received_at_server', $today)
                    ->whereIn('integrity_status', [IntegrityStatus::Suspicious, IntegrityStatus::Rejected])
                    ->count(),
            ],
        ]);
    }
}
