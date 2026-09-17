<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityPhoto;
use App\Models\CaptureSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityPhoto>
 */
class ActivityPhotoFactory extends Factory
{
    protected $model = ActivityPhoto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'capture_session_id' => CaptureSession::factory(),
            'device_id' => null,
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'accuracy' => fake()->randomFloat(1, 3, 20),
            'captured_at_device' => now(),
            'received_at_server' => now(),
            'source' => 'CAMERA',
            'sha256_hash' => hash('sha256', fake()->uuid()),
            'file_size' => fake()->numberBetween(200_000, 2_000_000),
            'mime_type' => 'image/jpeg',
            'storage_path' => 'activities/1/photos/'.fake()->uuid().'.jpg',
            'integrity_status' => 'PENDING',
        ];
    }
}
