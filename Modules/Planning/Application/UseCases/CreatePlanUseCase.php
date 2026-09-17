<?php

namespace Modules\Planning\Application\UseCases;

use App\Models\ActivityPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Planning\Domain\Enums\PlanStatus;

class CreatePlanUseCase
{
    /**
     * @param  array{idempotency_key: string, activity_type_id: int, location: string, planned_date: string, notes: ?string, product_ids: list<int>}  $data
     * @return array{plan: ActivityPlan, created: bool}
     */
    public function handle(User $creator, array $data): array
    {
        // docs section 22: a retried create (e.g. Android's WorkManager
        // after a dropped connection) must not create a duplicate plan —
        // the same idempotency_key from the same creator returns the
        // original instead.
        $existing = ActivityPlan::where('creator_id', $creator->id)
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existing) {
            return ['plan' => $existing->load(['activityType', 'products', 'creator']), 'created' => false];
        }

        $plan = DB::transaction(function () use ($creator, $data) {
            $plan = ActivityPlan::create([
                'creator_id' => $creator->id,
                'idempotency_key' => $data['idempotency_key'],
                'activity_type_id' => $data['activity_type_id'],
                'location' => $data['location'],
                'planned_date' => $data['planned_date'],
                'notes' => $data['notes'] ?? null,
                'status' => PlanStatus::Planned,
            ]);

            $plan->products()->sync($data['product_ids']);

            return $plan->load(['activityType', 'products', 'creator']);
        });

        return ['plan' => $plan, 'created' => true];
    }
}
