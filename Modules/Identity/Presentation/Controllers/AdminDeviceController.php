<?php

namespace Modules\Identity\Presentation\Controllers;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Identity\Application\UseCases\RevokeDeviceUseCase;
use Modules\Identity\Presentation\Resources\DeviceResource;

/**
 * Admin-facing device management (docs section 29.2: "device registration;
 * revoke device"), distinct from DeviceController::register which is the
 * mobile app managing its own device.
 */
class AdminDeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Device::query()->with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json(['data' => DeviceResource::collection($query->latest()->get())]);
    }

    public function revoke(Request $request, int $id, RevokeDeviceUseCase $useCase): JsonResponse
    {
        $device = Device::findOrFail($id);
        $device = $useCase->handle($device, $request->user());

        return response()->json(['data' => new DeviceResource($device)]);
    }
}
