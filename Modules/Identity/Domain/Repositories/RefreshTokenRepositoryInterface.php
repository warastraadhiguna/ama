<?php

namespace Modules\Identity\Domain\Repositories;

use App\Models\RefreshToken;
use App\Models\User;

interface RefreshTokenRepositoryInterface
{
    public function issue(User $user, ?int $deviceId, string $plainTextToken, \DateTimeInterface $expiresAt): RefreshToken;

    public function findValidByPlainTextToken(string $plainTextToken): ?RefreshToken;

    public function revoke(RefreshToken $refreshToken): void;
}
