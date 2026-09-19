<?php

namespace Modules\WebAdmin\Presentation\Controllers;

use App\Models\ActivityType;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Reports\Application\ActivityReport;
use Spatie\Permission\Models\Role;

/** docs section 29.6 (Reporting) — the page over Modules/Reports' ActivityReport. */
class ReportPageController extends Controller
{
    private const FILTERS = ['date_from', 'date_to', 'work_location_id', 'creator_id', 'activity_type_id', 'product_id'];

    public function index(Request $request, ActivityReport $report): InertiaResponse
    {
        return Inertia::render('Reports/Index', [
            'summary' => $report->summary($request),
            'filters' => $request->only(self::FILTERS),
            'filterOptions' => [
                'activityTypes' => ActivityType::orderBy('name')->get(['id', 'name']),
                'products' => Product::orderBy('name')->get(['id', 'name']),
                'workLocations' => WorkLocation::orderBy('name')->get(['id', 'name']),
                // See ActivityMonitoringController: User::role() throws for a missing role.
                'agronomists' => Role::where('name', 'AGRONOMIST')->exists()
                    ? User::role('AGRONOMIST')->orderBy('name')->get(['id', 'name'])
                    : collect(),
            ],
        ]);
    }

    public function export(Request $request, ActivityReport $report): Response
    {
        return response($report->csv($request), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="activities-report.csv"',
        ]);
    }
}
