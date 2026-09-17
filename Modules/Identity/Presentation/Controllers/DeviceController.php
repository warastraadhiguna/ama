<?php

namespace Modules\Identity\Presentation\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Identity\Application\UseCases\RegisterDeviceUseCase;
use Modules\Identity\Domain\Exceptions\DeviceRevokedException;
use Modules\Identity\Presentation\Requests\RegisterDeviceRequest;
use Modules\Identity\Presentation\Resources\DeviceResource;

class DeviceController extends Controller
{
    public function register(RegisterDeviceRequest $request, RegisterDeviceUseCase $useCase): JsonResponse
    {
        try {
            $device = $useCase->handle(
                user: $request->user(),
                deviceUuid: $request->string('device_uuid')->toString(),
                attributes: [
                    'app_version' => $request->string('app_version')->toString(),
                    'os_version' => $request->string('os_version')->toString() ?: null,
                    'manufacturer' => $request->string('manufacturer')->toString() ?: null,
                    'model' => $request->string('model')->toString() ?: null,
                ],
            );
        } catch (DeviceRevokedException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'DEVICE_REVOKED'], 403);
        }

        return response()->json(['device' => new DeviceResource($device)]);
    }
}
