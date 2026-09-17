<?php

namespace Modules\Identity\Presentation\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_uuid' => $this->device_uuid,
            'app_version' => $this->app_version,
            'os_version' => $this->os_version,
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'integrity_status' => $this->integrity_status,
            'last_active_at' => $this->last_active_at?->toIso8601String(),
        ];
    }
}
