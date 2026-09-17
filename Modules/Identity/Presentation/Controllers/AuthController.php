<?php

namespace Modules\Identity\Presentation\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Identity\Application\UseCases\LoginUseCase;
use Modules\Identity\Application\UseCases\LogoutUseCase;
use Modules\Identity\Application\UseCases\RefreshTokenUseCase;
use Modules\Identity\Domain\Exceptions\DeviceRevokedException;
use Modules\Identity\Domain\Exceptions\InvalidCredentialsException;
use Modules\Identity\Domain\Exceptions\InvalidRefreshTokenException;
use Modules\Identity\Presentation\Requests\LoginRequest;
use Modules\Identity\Presentation\Requests\LogoutRequest;
use Modules\Identity\Presentation\Requests\RefreshTokenRequest;
use Modules\Identity\Presentation\Resources\UserResource;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginUseCase $useCase): JsonResponse
    {
        try {
            $result = $useCase->handle(
                email: $request->string('email')->toString(),
                password: $request->string('password')->toString(),
                deviceUuid: $request->string('device_uuid')->toString() ?: null,
            );
        } catch (InvalidCredentialsException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        } catch (DeviceRevokedException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'DEVICE_REVOKED'], 403);
        }

        return $this->tokenResponse($result);
    }

    public function refresh(RefreshTokenRequest $request, RefreshTokenUseCase $useCase): JsonResponse
    {
        try {
            $result = $useCase->handle($request->string('refresh_token')->toString());
        } catch (InvalidRefreshTokenException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        } catch (DeviceRevokedException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'DEVICE_REVOKED'], 403);
        }

        return $this->tokenResponse($result);
    }

    public function logout(LogoutRequest $request, LogoutUseCase $useCase): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();

        $useCase->handle($user, $token, $request->string('refresh_token')->toString() ?: null);

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * @param  array{user: User, access_token: string, access_token_expires_at: Carbon, refresh_token: string, refresh_token_expires_at: Carbon}  $result
     */
    private function tokenResponse(array $result): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($result['user']),
            'access_token' => $result['access_token'],
            'access_token_expires_at' => $result['access_token_expires_at']->toIso8601String(),
            'refresh_token' => $result['refresh_token'],
            'refresh_token_expires_at' => $result['refresh_token_expires_at']->toIso8601String(),
        ]);
    }
}
