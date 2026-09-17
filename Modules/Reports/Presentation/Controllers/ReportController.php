<?php

namespace Modules\Reports\Presentation\Controllers;

use App\Models\Activity;
use App\Models\ActivityPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Planning\Domain\Enums\PlanStatus;

/**
 * docs section 29.6 / section 44 Q14 (export format: Excel/PDF/both —
 * unresolved). Default here: JSON aggregate for the summary numbers (which
 * a future Web Admin report page or any other consumer can render however
 * it wants) + a plain CSV for the row-level export, since CSV opens
 * natively in Excel and doesn't require picking/installing a PDF or
 * xlsx-specific library before the actual desired format is confirmed.
 */
class ReportController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        [$activities, $plans] = $this->filteredQueries($request);

        $totalPlans = (clone $plans)->count();
        $realizedPlans = (clone $plans)->where('status', PlanStatus::Realized)->count();
        $cancelledPlans = (clone $plans)->where('status', PlanStatus::Cancelled)->count();
        $eligiblePlans = $totalPlans - $cancelledPlans;

        return response()->json([
            'data' => [
                'activities_total' => (clone $activities)->count(),
                'plans_total' => $totalPlans,
                'plans_realized' => $realizedPlans,
                'plans_cancelled' => $cancelledPlans,
                // Cancelled plans were never meant to be realized, so they're
                // excluded from the denominator rather than counted against it.
                'realization_rate' => $eligiblePlans > 0 ? round($realizedPlans / $eligiblePlans, 4) : null,
                'by_integrity_status' => (clone $activities)
                    ->join('activity_locations', 'activity_locations.activity_id', '=', 'activities.id')
                    ->selectRaw('activity_locations.integrity_status, count(distinct activities.id) as count')
                    ->groupBy('activity_locations.integrity_status')
                    ->pluck('count', 'integrity_status'),
            ],
        ]);
    }

    public function export(Request $request): Response
    {
        [$activities] = $this->filteredQueries($request);

        $rows = $activities->with(['activityType', 'products', 'creator.workLocation'])->get();

        $csv = "id,date,creator,work_location,activity_type,products,location,status\n";
        foreach ($rows as $activity) {
            $csv .= implode(',', array_map(
                fn ($value) => '"'.str_replace('"', '""', (string) $value).'"',
                [
                    $activity->id,
                    $activity->created_at->toDateString(),
                    $activity->creator->name,
                    $activity->creator->workLocation?->name,
                    $activity->activityType->name,
                    $activity->products->pluck('name')->implode('; '),
                    $activity->location,
                    $activity->status->value,
                ],
            ))."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="activities-report.csv"',
        ]);
    }

    /**
     * @return array{0: Builder<Activity>, 1: Builder<ActivityPlan>}
     */
    private function filteredQueries(Request $request): array
    {
        $activities = Activity::query();
        $plans = ActivityPlan::query();

        if ($request->filled('date_from')) {
            $activities->whereDate('created_at', '>=', $request->date('date_from'));
            $plans->whereDate('planned_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $activities->whereDate('created_at', '<=', $request->date('date_to'));
            $plans->whereDate('planned_date', '<=', $request->date('date_to'));
        }

        if ($request->filled('creator_id')) {
            $activities->where('creator_id', $request->integer('creator_id'));
            $plans->where('creator_id', $request->integer('creator_id'));
        }

        if ($request->filled('activity_type_id')) {
            $activities->where('activity_type_id', $request->integer('activity_type_id'));
            $plans->where('activity_type_id', $request->integer('activity_type_id'));
        }

        if ($request->filled('work_location_id')) {
            $workLocationId = $request->integer('work_location_id');
            $activities->whereHas('creator', fn ($q) => $q->where('work_location_id', $workLocationId));
            $plans->whereHas('creator', fn ($q) => $q->where('work_location_id', $workLocationId));
        }

        if ($request->filled('product_id')) {
            $productId = $request->integer('product_id');
            $activities->whereHas('products', fn ($q) => $q->where('products.id', $productId));
            $plans->whereHas('products', fn ($q) => $q->where('products.id', $productId));
        }

        return [$activities, $plans];
    }
}
