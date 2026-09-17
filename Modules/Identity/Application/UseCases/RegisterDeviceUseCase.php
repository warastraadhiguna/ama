<?php

namespace Modules\Identity\Application\UseCases;

use App\Models\Device;
use App\Models\User;
use Modules\Identity\Domain\Exceptions\DeviceRevokedException;
use Modules\Identity\Domain\Repositories\DeviceRepositoryInterface;

class RegisterDeviceUseCase
{
    public function __construct(
        private readonly DeviceRepositoryInterface $devices,
    ) {}

    /**
     * @param  array{app_version: string, os_version: ?string, manufacturer: ?string, model: ?string}  $attributes
     */
    public function handle(User $user, string $deviceUuid, array $attributes): Device
    {
        $existing = $this->devices->findByUuid($deviceUuid);

        if ($existing instanceof Device && $existing->isRevoked()) {
            throw new DeviceRevokedException;
        }

        if ($existing instanceof Device && $existing->user_id !== $user->id) {
            // A revoked-or-not device UUID stays bound to its original account;
            // re-registering it under a different user is not allowed.
            throw new DeviceRevokedException;
        }

        return $this->devices->registerOrUpdate($user, $deviceUuid, $attributes);
    }
}
