<?php

namespace Modules\Planning\Presentation\Resources;

use App\Models\ActivityPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityPlan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'location' => $this->location,
            'planned_date' => $this->planned_date->toDateString(),
            'notes' => $this->notes,
            'activity_type' => $this->whenLoaded('activityType', fn () => [
                'id' => $this->activityType->id,
                'name' => $this->activityType->name,
            ]),
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($product) => [
                'id' => $product->id,
                'name' => $product->name,
            ])),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
