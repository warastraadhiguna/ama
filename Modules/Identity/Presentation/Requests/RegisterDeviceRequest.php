<?php

namespace Modules\Identity\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
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
            'app_version' => ['required', 'string', 'max:32'],
            'os_version' => ['nullable', 'string', 'max:32'],
            'manufacturer' => ['nullable', 'string', 'max:64'],
            'model' => ['nullable', 'string', 'max:64'],
        ];
    }
}
