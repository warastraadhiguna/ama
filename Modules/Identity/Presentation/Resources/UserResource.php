<?php

namespace Modules\Identity\Presentation\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nip' => $this->nip,
            'email' => $this->email,
            'phone' => $this->phone,
            'position' => $this->whenLoaded('position', fn () => [
                'id' => $this->position->id,
                'name' => $this->position->name,
            ]),
            'work_location' => $this->whenLoaded('workLocation', fn () => [
                'id' => $this->workLocation->id,
                'name' => $this->workLocation->name,
            ]),
            'photo_path' => $this->photo_path,
            'is_active' => $this->is_active,
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
