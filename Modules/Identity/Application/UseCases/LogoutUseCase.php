<?php

namespace Modules\Identity\Application\UseCases;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Identity\Domain\Repositories\RefreshTokenRepositoryInterface;

class LogoutUseCase
{
    public function __construct(
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
    ) {}

    public function handle(User $user, PersonalAccessToken $currentAccessToken, ?string $plainTextRefreshToken): void
    {
        $currentAccessToken->delete();

        if ($plainTextRefreshToken) {
            $refreshToken = $this->refreshTokens->findValidByPlainTextToken($plainTextRefreshToken);

            if ($refreshToken && $refreshToken->user_id === $user->id) {
                $this->refreshTokens->revoke($refreshToken);
            }
        }
    }
}
