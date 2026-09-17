<?php

namespace Modules\Identity\Application\UseCases;

use App\Models\Device;
use App\Models\User;
use Modules\Audit\Application\AuditLogger;

/**
 * docs section 24: "Admin dapat melakukan revoke device." The data model
 * (devices.revoked_at) and every check against it (login, evidence
 * submission, device registration) existed since Milestone B/F — this is
 * the actual admin action that sets it, which nothing exposed until now.
 */
class RevokeDeviceUseCase
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Device $device, User $actor): Device
    {
        $device->update(['revoked_at' => now()]);

        $this->auditLogger->log($actor, 'revoked', Device::class, $device->id, newValues: [
            'device_uuid' => $device->device_uuid,
            'user_id' => $device->user_id,
        ]);

        return $device;
    }
}
