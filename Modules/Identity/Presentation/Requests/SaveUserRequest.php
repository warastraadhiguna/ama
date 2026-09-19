<?php

namespace Modules\Identity\Presentation\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * Create/update payload for Web Admin user management (docs section 29.2).
 * Authorization (users.manage) is enforced by route middleware.
 */
class SaveUserRequest extends FormRequest
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
        $editing = $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editing)],
            'nip' => ['nullable', 'string', 'max:64', Rule::unique('users', 'nip')->ignore($editing)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', 'string', Rule::in(self::assignableRoles($this->user()))],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'work_location_id' => ['nullable', 'integer', 'exists:work_locations,id'],
            'is_active' => ['required', 'boolean'],
            'password' => [$editing ? 'nullable' : 'required', 'string', Password::min(8)],
        ];
    }

    /**
     * Only a SUPER_ADMIN may hand out SUPER_ADMIN — otherwise an ADMIN
     * (who has users.manage) could promote themselves past their own role.
     *
     * @return array<int, string>
     */
    public static function assignableRoles(?User $actor): array
    {
        $roles = Role::query()->orderBy('name')->pluck('name');

        if (! $actor?->hasRole('SUPER_ADMIN')) {
            $roles = $roles->reject(fn (string $name) => $name === 'SUPER_ADMIN');
        }

        return $roles->values()->all();
    }
}
