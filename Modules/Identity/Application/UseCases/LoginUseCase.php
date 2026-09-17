<?php

namespace Modules\Identity\Application\UseCases;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Exceptions\DeviceRevokedException;
use Modules\Identity\Domain\Exceptions\InvalidCredentialsException;
use Modules\Identity\Domain\Repositories\DeviceRepositoryInterface;
use Modules\Identity\Domain\Repositories\RefreshTokenRepositoryInterface;

class LoginUseCase
{
    public function __construct(
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly DeviceRepositoryInterface $devices,
    ) {}

    /**
     * @return array{user: User, access_token: string, access_token_expires_at: Carbon, refresh_token: string, refresh_token_expires_at: Carbon}
     */
    public function handle(string $email, string $password, ?string $deviceUuid): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password) || ! $user->is_active) {
            throw new InvalidCredentialsException;
        }

        $device = $deviceUuid ? $this->devices->findByUuid($deviceUuid) : null;

        if ($device instanceof Device && $device->isRevoked()) {
            throw new DeviceRevokedException;
        }

        if ($device instanceof Device && $device->user_id !== $user->id) {
            // A device UUID is bound to whichever account registered it first.
            throw new DeviceRevokedException;
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $accessTokenTtl = now()->addMinutes((int) config('identity.access_token_ttl_minutes'));
        $accessToken = $user->createToken(
            name: $device?->device_uuid ?? 'login',
            expiresAt: $accessTokenTtl,
        )->plainTextToken;

        $refreshTokenTtl = now()->addDays((int) config('identity.refresh_token_ttl_days'));
        $refreshTokenPlain = Str::random(64);
        $this->refreshTokens->issue($user, $device?->id, $refreshTokenPlain, $refreshTokenTtl);

        return [
            'user' => $user,
            'access_token' => $accessToken,
            'access_token_expires_at' => $accessTokenTtl,
            'refresh_token' => $refreshTokenPlain,
            'refresh_token_expires_at' => $refreshTokenTtl,
        ];
    }
}
