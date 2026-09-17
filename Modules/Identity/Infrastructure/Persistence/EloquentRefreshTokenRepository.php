<?php

namespace Modules\Identity\Infrastructure\Persistence;

use App\Models\RefreshToken;
use App\Models\User;
use Modules\Identity\Domain\Repositories\RefreshTokenRepositoryInterface;

class EloquentRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function issue(User $user, ?int $deviceId, string $plainTextToken, \DateTimeInterface $expiresAt): RefreshToken
    {
        return RefreshToken::create([
            'user_id' => $user->id,
            'device_id' => $deviceId,
            'token_hash' => $this->hash($plainTextToken),
            'expires_at' => $expiresAt,
        ]);
    }

    public function findValidByPlainTextToken(string $plainTextToken): ?RefreshToken
    {
        // The token itself is a high-entropy random value (not a low-entropy
        // password), so a fast indexed hash is used for lookup instead of
        // bcrypt — this keeps refresh under one query at any scale.
        return RefreshToken::query()
            ->where('token_hash', $this->hash($plainTextToken))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    private function hash(string $plainTextToken): string
    {
        return hash('sha256', $plainTextToken);
    }

    public function revoke(RefreshToken $refreshToken): void
    {
        $refreshToken->update(['revoked_at' => now()]);
    }
}
