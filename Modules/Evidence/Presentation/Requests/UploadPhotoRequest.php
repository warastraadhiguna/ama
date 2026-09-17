<?php

namespace Modules\Evidence\Presentation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadPhotoRequest extends FormRequest
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
            // docs section 17.4: avoid raw 5-15MB camera originals reaching
            // the server — 15360 KB is the reject ceiling, not a target;
            // real compression happens client-side before upload.
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:15360'],
            'capture_session_uuid' => ['required', 'uuid'],
            'started_at' => ['required', 'date'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0'],
            'captured_at_device' => ['required', 'date'],
        ];
    }
}
