<?php

namespace Modules\Integrity\Presentation\Controllers;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Integrity\Application\UseCases\VerifyPlayIntegrityUseCase;
use Modules\Integrity\Presentation\Requests\VerifyPlayIntegrityRequest;

class IntegrityController extends Controller
{
    public function verifyPlay(VerifyPlayIntegrityRequest $request, VerifyPlayIntegrityUseCase $useCase): JsonResponse
    {
        $device = Device::where('user_id', $request->user()->id)
            ->where('device_uuid', $request->string('device_uuid')->toString())
            ->firstOrFail();

        $result = $useCase->handle($device, $request->string('integrity_token')->toString());

        return response()->json([
            'configured' => $result->configured,
            'device_integrity_status' => $device->fresh()->integrity_status->value,
            'detail' => $result->detail,
        ]);
    }
}
