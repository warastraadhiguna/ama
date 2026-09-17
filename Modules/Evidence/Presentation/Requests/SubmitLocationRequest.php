<?php

namespace Modules\Evidence\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitLocationRequest extends FormRequest
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
            'capture_session_uuid' => ['required', 'uuid'],
            'started_at' => ['required', 'date'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0'],
            'altitude' => ['nullable', 'numeric'],
            'speed' => ['nullable', 'numeric', 'min:0'],
            'bearing' => ['nullable', 'numeric', 'between:0,360'],
            'provider' => ['nullable', 'string', 'max:32'],
            // docs section 19.2 (Layer 1) — the Android indicator, taken as
            // one input into the server-side evaluation, never trusted
            // alone (docs section 43).
            'is_mock_location' => ['required', 'boolean'],
            'captured_at_device' => ['required', 'date'],
        ];
    }
}
