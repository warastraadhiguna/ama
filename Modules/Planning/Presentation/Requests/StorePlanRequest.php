<?php

namespace Modules\Planning\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePlanRequest extends FormRequest
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
            'activity_type_id' => ['required', 'integer', 'exists:activity_types,id'],
            'location' => ['required', 'string', 'max:255'],
            'planned_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }
}
