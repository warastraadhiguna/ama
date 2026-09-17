<?php

namespace Modules\Planning\Application\UseCases;

use App\Models\ActivityPlan;
use Illuminate\Support\Facades\DB;
use Modules\Planning\Domain\Enums\PlanStatus;
use Modules\Planning\Domain\Exceptions\InvalidPlanStatusTransitionException;
use Modules\Planning\Domain\Exceptions\PlanNotEditableException;

class UpdatePlanUseCase
{
    /**
     * @param  array{activity_type_id?: int, location?: string, planned_date?: string, notes?: ?string, product_ids?: list<int>, status?: string}  $data
     */
    public function handle(ActivityPlan $plan, array $data): ActivityPlan
    {
        if (! $plan->status->isEditable()) {
            throw new PlanNotEditableException;
        }

        if (isset($data['status'])) {
            $targetStatus = PlanStatus::from($data['status']);

            if (! $plan->status->canTransitionViaApiTo($targetStatus)) {
                throw new InvalidPlanStatusTransitionException($plan->status, $targetStatus);
            }
        }

        return DB::transaction(function () use ($plan, $data) {
            // Only fields actually present in $data are touched (PATCH-like
            // partial update semantics), including an explicit null for
            // `notes` (clearing it) — unlike the others it's nullable.
            foreach (['activity_type_id', 'location', 'planned_date'] as $field) {
                if (isset($data[$field])) {
                    $plan->{$field} = $data[$field];
                }
            }

            if (array_key_exists('notes', $data)) {
                $plan->notes = $data['notes'];
            }

            if (isset($data['status'])) {
                $plan->status = PlanStatus::from($data['status']);
            }

            $plan->save();

            if (isset($data['product_ids'])) {
                $plan->products()->sync($data['product_ids']);
            }

            return $plan->fresh(['activityType', 'products', 'creator']);
        });
    }
}
