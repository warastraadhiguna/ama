<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Activities\Domain\Enums\ActivityStatus;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_plan_id' => null,
            'creator_id' => User::factory(),
            'activity_type_id' => ActivityType::factory(),
            'location' => fake()->streetAddress(),
            'notes' => null,
            'status' => ActivityStatus::Draft,
        ];
    }
}
