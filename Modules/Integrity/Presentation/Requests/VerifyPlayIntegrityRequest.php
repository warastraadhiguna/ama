<?php

namespace Modules\Integrity\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPlayIntegrityRequest extends FormRequest
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
            'device_uuid' => ['required', 'uuid'],
            'integrity_token' => ['required', 'string'],
        ];
    }
}
