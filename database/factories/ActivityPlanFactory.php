<?php

namespace Database\Factories;

use App\Models\ActivityPlan;
use App\Models\ActivityType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Planning\Domain\Enums\PlanStatus;

/**
 * @extends Factory<ActivityPlan>
 */
class ActivityPlanFactory extends Factory
{
    protected $model = ActivityPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creator_id' => User::factory(),
            'activity_type_id' => ActivityType::factory(),
            'location' => fake()->streetAddress(),
            'planned_date' => fake()->dateTimeBetween('now', '+2 weeks')->format('Y-m-d'),
            'notes' => null,
            'status' => PlanStatus::Planned,
        ];
    }
}
