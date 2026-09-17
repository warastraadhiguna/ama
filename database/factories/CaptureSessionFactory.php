<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\CaptureSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CaptureSession>
 */
class CaptureSessionFactory extends Factory
{
    protected $model = CaptureSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'device_id' => null,
            'client_uuid' => (string) Str::uuid(),
            'started_at' => now(),
            'submitted_at' => null,
        ];
    }
}
