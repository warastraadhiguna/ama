<?php

namespace Modules\Identity\Domain\Repositories;

use App\Models\Device;
use App\Models\User;

interface DeviceRepositoryInterface
{
    public function findByUuid(string $deviceUuid): ?Device;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function registerOrUpdate(User $user, string $deviceUuid, array $attributes): Device;
}
