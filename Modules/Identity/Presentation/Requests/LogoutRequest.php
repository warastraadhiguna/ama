<?php

namespace Modules\Identity\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogoutRequest extends FormRequest
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
            'refresh_token' => ['nullable', 'string'],
        ];
    }
}
