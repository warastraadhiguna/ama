<?php

namespace Modules\Evidence\Application\Support;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The mobile login flow (Modules/Identity) names the Sanctum access token
 * after the device_uuid, so the device tied to the current request can be
 * recovered from the token itself instead of asking the client to repeat
 * device_uuid on every evidence call. When login happened without a
 * device_uuid, LoginUseCase falls back to the literal token name "login"
 * (or "refresh") instead of a UUID — device_uuid is a uuid column, so that
 * has to be filtered out here or the lookup query itself errors.
 */
class ResolveCurrentDevice
{
    public function handle(User $user, ?string $accessTokenName): ?Device
    {
        if (! $accessTokenName || ! Str::isUuid($accessTokenName)) {
            return null;
        }

        return Device::where('user_id', $user->id)
            ->where('device_uuid', $accessTokenName)
            ->first();
    }
}
