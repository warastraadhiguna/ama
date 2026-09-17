<?php

namespace Modules\Identity\Infrastructure\Persistence;

use App\Models\Device;
use App\Models\User;
use Modules\Identity\Domain\Repositories\DeviceRepositoryInterface;

class EloquentDeviceRepository implements DeviceRepositoryInterface
{
    public function findByUuid(string $deviceUuid): ?Device
    {
        return Device::where('device_uuid', $deviceUuid)->first();
    }

    public function registerOrUpdate(User $user, string $deviceUuid, array $attributes): Device
    {
        return Device::updateOrCreate(
            ['device_uuid' => $deviceUuid, 'user_id' => $user->id],
            [...$attributes, 'last_active_at' => now()],
        );
    }
}
