<?php

namespace Modules\Reports\Application;

use App\Models\Activity;
use App\Models\ActivityPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Planning\Domain\Enums\PlanStatus;

/**
 * docs section 29.6 / section 44 Q14 (export format: Excel/PDF/both —
 * unresolved). Default: aggregate numbers + a plain CSV for the row-level
 * export, since CSV opens natively in Excel and doesn't require picking a
 * PDF or xlsx library before the desired format is confirmed.
 *
 * One place for the filters and numbers so the API (mobile/other clients)
 * and the Web Admin report page can never disagree.
 */
class ActivityReport
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Request $request): array
    {
        [$activities, $plans] = $this->filteredQueries($request);

        $totalPlans = (clone $plans)->count();
        $realizedPlans = (clone $plans)->where('status', PlanStatus::Realized)->count();
        $cancelledPlans = (clone $plans)->where('status', PlanStatus::Cancelled)->count();
        $eligiblePlans = $totalPlans - $cancelledPlans;

        return [
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
                ->pluck('count', 'integrity_status')
                ->all(),
        ];
    }

    public function csv(Request $request): string
    {
        [$activities] = $this->filteredQueries($request);

        $rows = $activities->with(['activityType', 'products', 'creator.workLocation'])->get();

        $csv = "id,date,creator,work_location,activity_type,products,location,status\n";
        foreach ($rows as $activity) {
            $csv .= implode(',', array_map(
                fn ($value) => '"'.str_replace('"', '""', self::neutralizeFormula((string) $value)).'"',
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

        return $csv;
    }

    /**
     * `location` (and names) are free text typed by field users. A cell that
     * starts with = + - @ (or a tab/CR) is executed as a formula when the
     * CSV is opened in Excel/Sheets, so such values are prefixed with a
     * single quote — the standard CSV-injection mitigation.
     */
    private static function neutralizeFormula(string $value): string
    {
        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
    }

    /**
     * @return array{0: Builder<Activity>, 1: Builder<ActivityPlan>}
     */
    private function filteredQueries(Request $request): array
    {
        $activities = Activity::query();
        $plans = ActivityPlan::query();

        if ($request->filled('date_from')) {
            $activities->whereDate('activities.created_at', '>=', $request->date('date_from'));
            $plans->whereDate('planned_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $activities->whereDate('activities.created_at', '<=', $request->date('date_to'));
            $plans->whereDate('planned_date', '<=', $request->date('date_to'));
        }

        if ($request->filled('creator_id')) {
            $activities->where('activities.creator_id', $request->integer('creator_id'));
            $plans->where('creator_id', $request->integer('creator_id'));
        }

        if ($request->filled('activity_type_id')) {
            $activities->where('activities.activity_type_id', $request->integer('activity_type_id'));
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
