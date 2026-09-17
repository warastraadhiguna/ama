<?php

namespace Modules\Identity\Application\UseCases;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Exceptions\DeviceRevokedException;
use Modules\Identity\Domain\Exceptions\InvalidRefreshTokenException;
use Modules\Identity\Domain\Repositories\RefreshTokenRepositoryInterface;

class RefreshTokenUseCase
{
    public function __construct(
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
    ) {}

    /**
     * @return array{user: User, access_token: string, access_token_expires_at: Carbon, refresh_token: string, refresh_token_expires_at: Carbon}
     */
    public function handle(string $plainTextRefreshToken): array
    {
        $existing = $this->refreshTokens->findValidByPlainTextToken($plainTextRefreshToken);

        if (! $existing) {
            throw new InvalidRefreshTokenException;
        }

        $device = $existing->device;

        if ($device instanceof Device && $device->isRevoked()) {
            throw new DeviceRevokedException;
        }

        // Rotate: the old refresh token is single-use, a fresh one replaces it.
        $this->refreshTokens->revoke($existing);

        $user = $existing->user;

        $accessTokenTtl = now()->addMinutes((int) config('identity.access_token_ttl_minutes'));
        $accessToken = $user->createToken(
            name: $device?->device_uuid ?? 'refresh',
            expiresAt: $accessTokenTtl,
        )->plainTextToken;

        $refreshTokenTtl = now()->addDays((int) config('identity.refresh_token_ttl_days'));
        $refreshTokenPlain = Str::random(64);
        $this->refreshTokens->issue($user, $existing->device_id, $refreshTokenPlain, $refreshTokenTtl);

        return [
            'user' => $user,
            'access_token' => $accessToken,
            'access_token_expires_at' => $accessTokenTtl,
            'refresh_token' => $refreshTokenPlain,
            'refresh_token_expires_at' => $refreshTokenTtl,
        ];
    }
}
