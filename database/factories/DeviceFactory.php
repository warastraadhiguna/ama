<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_uuid' => Str::uuid()->toString(),
            'app_version' => '1.0.0',
            'os_version' => 'Android 14',
            'manufacturer' => 'Samsung',
            'model' => 'SM-A146P',
            'integrity_status' => 'TRUSTED',
            'last_active_at' => now(),
        ];
    }
}
