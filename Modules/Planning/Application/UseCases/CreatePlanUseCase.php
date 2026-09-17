<?php

namespace Modules\Planning\Application\UseCases;

use App\Models\ActivityPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Planning\Domain\Enums\PlanStatus;

class CreatePlanUseCase
{
    /**
     * @param  array{activity_type_id: int, location: string, planned_date: string, notes: ?string, product_ids: list<int>}  $data
     */
    public function handle(User $creator, array $data): ActivityPlan
    {
        return DB::transaction(function () use ($creator, $data) {
            $plan = ActivityPlan::create([
                'creator_id' => $creator->id,
                'activity_type_id' => $data['activity_type_id'],
                'location' => $data['location'],
                'planned_date' => $data['planned_date'],
                'notes' => $data['notes'] ?? null,
                'status' => PlanStatus::Planned,
            ]);

            $plan->products()->sync($data['product_ids']);

            return $plan->load(['activityType', 'products', 'creator']);
        });
    }
}
