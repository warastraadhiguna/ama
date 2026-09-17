<?php

namespace Modules\Activities\Application\UseCases;

use App\Models\Activity;
use App\Models\ActivityPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Activities\Domain\Enums\ActivityStatus;
use Modules\Activities\Domain\Exceptions\PlanNotRealizableException;
use Modules\Planning\Domain\Enums\PlanStatus;

class CreateActivityUseCase
{
    /**
     * @param  array{idempotency_key: string, activity_plan_id: ?int, activity_type_id: ?int, location: string, notes: ?string, product_ids: list<int>}  $data
     * @return array{activity: Activity, created: bool}
     */
    public function handle(User $creator, array $data): array
    {
        // docs section 22: a retried create must not realize the same
        // activity twice (or, for a from-plan realization, re-consume the
        // plan a second time).
        $existing = Activity::where('creator_id', $creator->id)
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existing) {
            return ['activity' => $existing->load(['activityType', 'products', 'creator', 'plan']), 'created' => false];
        }

        $activity = ($data['activity_plan_id'] ?? null)
            ? $this->realizeFromPlan($creator, (int) $data['activity_plan_id'], $data)
            : $this->realizeManually($creator, $data);

        return ['activity' => $activity, 'created' => true];
    }

    /**
     * @param  array{idempotency_key: string, location: string, notes: ?string}  $data
     */
    private function realizeFromPlan(User $creator, int $planId, array $data): Activity
    {
        return DB::transaction(function () use ($creator, $planId, $data) {
            /** @var ActivityPlan $plan */
            $plan = ActivityPlan::with('products')->lockForUpdate()->findOrFail($planId);

            if ($plan->creator_id !== $creator->id) {
                throw new PlanNotRealizableException('it belongs to a different user.');
            }

            if (! $plan->status->isEditable()) {
                throw new PlanNotRealizableException("it is already {$plan->status->value}.");
            }

            // docs section 14.1: activity type and products are loaded from
            // the plan automatically, not re-entered by the user.
            $activity = Activity::create([
                'activity_plan_id' => $plan->id,
                'creator_id' => $creator->id,
                'idempotency_key' => $data['idempotency_key'],
                'activity_type_id' => $plan->activity_type_id,
                'location' => $data['location'],
                'notes' => $data['notes'] ?? null,
                'status' => ActivityStatus::Draft,
            ]);
            $activity->products()->sync($plan->products->pluck('id'));

            $plan->update([
                'status' => PlanStatus::Realized,
                'realized_activity_id' => $activity->id,
            ]);

            return $activity->load(['activityType', 'products', 'creator', 'plan']);
        });
    }

    /**
     * @param  array{idempotency_key: string, activity_type_id: int, location: string, notes: ?string, product_ids: list<int>}  $data
     */
    private function realizeManually(User $creator, array $data): Activity
    {
        return DB::transaction(function () use ($creator, $data) {
            $activity = Activity::create([
                'activity_plan_id' => null,
                'creator_id' => $creator->id,
                'idempotency_key' => $data['idempotency_key'],
                'activity_type_id' => $data['activity_type_id'],
                'location' => $data['location'],
                'notes' => $data['notes'] ?? null,
                'status' => ActivityStatus::Draft,
            ]);
            $activity->products()->sync($data['product_ids']);

            return $activity->load(['activityType', 'products', 'creator', 'plan']);
        });
    }
}
