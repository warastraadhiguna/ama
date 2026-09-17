<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityLocation;
use App\Models\CaptureSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLocation>
 */
class ActivityLocationFactory extends Factory
{
    protected $model = ActivityLocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'capture_session_id' => CaptureSession::factory(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'accuracy' => fake()->randomFloat(1, 3, 20),
            'altitude' => null,
            'speed' => null,
            'bearing' => null,
            'provider' => 'fused',
            'captured_at_device' => now(),
            'received_at_server' => now(),
        ];
    }
}
