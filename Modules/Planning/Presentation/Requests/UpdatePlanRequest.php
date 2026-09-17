<?php

namespace Modules\Planning\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'activity_type_id' => ['sometimes', 'integer', 'exists:activity_types,id'],
            'location' => ['sometimes', 'string', 'max:255'],
            'planned_date' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'product_ids' => ['sometimes', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            // REALIZED is intentionally excluded: it's set by the Activities
            // module (Milestone E) when a realization is linked, never
            // directly through this endpoint.
            'status' => ['sometimes', 'string', 'in:PLANNED,READY,CANCELLED'],
        ];
    }
}
