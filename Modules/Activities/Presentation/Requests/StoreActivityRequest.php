<?php

namespace Modules\Activities\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityRequest extends FormRequest
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
            // docs section 22: client-generated, so a retried request after
            // a dropped connection is safe to resend as-is.
            'idempotency_key' => ['required', 'uuid'],
            'activity_plan_id' => ['nullable', 'integer', 'exists:activity_plans,id'],
            // Required only for manual realization — when activity_plan_id
            // is set, activity type and products are copied from the plan
            // (docs section 14.1) rather than re-entered here.
            'activity_type_id' => ['required_without:activity_plan_id', 'integer', 'exists:activity_types,id'],
            'location' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'product_ids' => ['required_without:activity_plan_id', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:products,id'],
        ];
    }
}
