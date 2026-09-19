<?php

namespace Modules\WebAdmin\Presentation\Controllers;

use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Models\ActivityPlan;
use App\Models\ActivityType;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Integrity\Domain\Enums\IntegrityStatus;
use Modules\Planning\Domain\Enums\PlanStatus;
use Spatie\Permission\Models\Role;

/**
 * docs section 29.1. The reference day defaults to today in the server's
 * timezone — the doc doesn't specify per-territory timezones and this is a
 * single-country (Indonesia) deployment, so one clock is a reasonable
 * default rather than an OPEN QUESTION worth blocking on. Filters (docs
 * 29.1: date, area, agronomist, activity type, product) narrow every card;
 * the "status" filter the doc also lists has no meaning for cards that are
 * themselves status counts, so it lives on the Activities list instead.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $day = $request->filled('date') ? $request->date('date')->startOfDay() : now()->startOfDay();

        $creatorId = $request->filled('creator_id') ? $request->integer('creator_id') : null;
        $workLocationId = $request->filled('work_location_id') ? $request->integer('work_location_id') : null;
        $typeId = $request->filled('activity_type_id') ? $request->integer('activity_type_id') : null;
        $productId = $request->filled('product_id') ? $request->integer('product_id') : null;

        // Activities and plans share these columns/relations, so one scope serves both.
        $scope = function (Builder $query) use ($creatorId, $workLocationId, $typeId, $productId): Builder {
            return $query
                ->when($creatorId, fn (Builder $q) => $q->where('creator_id', $creatorId))
                ->when($typeId, fn (Builder $q) => $q->where('activity_type_id', $typeId))
                ->when($workLocationId, fn (Builder $q) => $q->whereHas(
                    'creator',
                    fn ($c) => $c->where('work_location_id', $workLocationId),
                ))
                ->when($productId, fn (Builder $q) => $q->whereHas(
                    'products',
                    fn ($p) => $p->where('products.id', $productId),
                ));
        };

        return Inertia::render('Dashboard', [
            'summary' => [
                'activities_today' => $scope(Activity::query())->whereDate('created_at', $day)->count(),
                'planned_today' => $scope(ActivityPlan::query())->whereDate('planned_date', $day)->count(),
                'not_realized' => $scope(ActivityPlan::query())
                    ->whereDate('planned_date', '<=', $day)
                    ->whereIn('status', [PlanStatus::Planned, PlanStatus::Ready])
                    ->count(),
                'location_alerts' => ActivityLocation::query()
                    ->whereDate('received_at_server', $day)
                    ->whereIn('integrity_status', [IntegrityStatus::Suspicious, IntegrityStatus::Rejected])
                    ->whereHas('activity', fn (Builder $q) => $scope($q))
                    ->count(),
            ],
            'day' => $day->toDateString(),
            'isToday' => $day->isToday(),
            'filters' => $request->only(['date', 'work_location_id', 'creator_id', 'activity_type_id', 'product_id']),
            'filterOptions' => [
                'activityTypes' => ActivityType::orderBy('name')->get(['id', 'name']),
                'products' => Product::orderBy('name')->get(['id', 'name']),
                'workLocations' => WorkLocation::orderBy('name')->get(['id', 'name']),
                // User::role() throws if the role doesn't exist yet.
                'agronomists' => Role::where('name', 'AGRONOMIST')->exists()
                    ? User::role('AGRONOMIST')->orderBy('name')->get(['id', 'name'])
                    : collect(),
            ],
        ]);
    }
}
