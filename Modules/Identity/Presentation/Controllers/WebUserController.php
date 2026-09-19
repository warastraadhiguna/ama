<?php

namespace Modules\Identity\Presentation\Controllers;

use App\Models\Device;
use App\Models\Position;
use App\Models\RefreshToken;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Audit\Application\AuditLogger;
use Modules\Identity\Application\UseCases\RevokeDeviceUseCase;
use Modules\Identity\Presentation\Requests\SaveUserRequest;

/**
 * docs section 29.2 (User Management): CRUD, role, position, work
 * location, activate/deactivate, device list + revoke. Users are never
 * hard-deleted — their activities/plans/audit rows reference them — so
 * "delete" is deactivation (docs section 30 also prefers soft handling
 * for important data).
 */
class WebUserController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): Response
    {
        $query = User::query()->with(['roles', 'position', 'workLocation'])->orderBy('name');

        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->trim().'%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $term)
                ->orWhere('email', 'ilike', $term)
                ->orWhere('nip', 'ilike', $term));
        }

        $users = $query->paginate(20)->withQueryString();
        $users->through(fn (User $user) => $this->summarize($user));

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $request->string('search')->toString()],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Users/Form', [
            'user' => null,
            'devices' => [],
            'options' => $this->options($request->user()),
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create(collect($data)->except('role')->all());
        $user->syncRoles([$data['role']]);

        $this->auditLogger->log($request->user(), 'created', User::class, $user->id, newValues: $this->auditable($user));

        return redirect('/users')->with('success', "Pengguna {$user->name} dibuat.");
    }

    public function edit(Request $request, int $id): Response
    {
        $user = User::with(['roles', 'devices'])->findOrFail($id);

        return Inertia::render('Users/Form', [
            'user' => $this->summarize($user),
            'devices' => $user->devices()->latest()->get()->map(fn (Device $device) => [
                'id' => $device->id,
                'label' => trim(($device->manufacturer ?? '').' '.($device->model ?? '')) ?: $device->device_uuid,
                'app_version' => $device->app_version,
                'last_active_at' => $device->last_active_at?->toIso8601String(),
                'revoked_at' => $device->revoked_at?->toIso8601String(),
            ]),
            'options' => $this->options($request->user()),
        ]);
    }

    public function update(SaveUserRequest $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $actor = $request->user();
        $data = $request->validated();

        // Editing yourself is fine, but locking yourself out (or dropping
        // your own admin role) would leave nobody able to undo it from here.
        if ($user->is($actor) && ! $data['is_active']) {
            return back()->withErrors(['is_active' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.']);
        }

        if ($user->is($actor) && $data['role'] !== $user->getRoleNames()->first()) {
            return back()->withErrors(['role' => 'Anda tidak dapat mengubah role Anda sendiri.']);
        }

        // A plain ADMIN must not be able to edit a SUPER_ADMIN's account.
        if ($user->hasRole('SUPER_ADMIN') && ! $actor->hasRole('SUPER_ADMIN')) {
            abort(403);
        }

        $old = $this->auditable($user);
        $wasActive = $user->is_active;

        $attributes = collect($data)->except(['role', 'password'])->all();
        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        $user->update($attributes);
        $user->syncRoles([$data['role']]);

        if ($wasActive && ! $user->is_active) {
            $this->cutOffSessions($user);
        }

        $this->auditLogger->log($actor, 'updated', User::class, $user->id, $old, $this->auditable($user->fresh()));

        return redirect('/users')->with('success', "Pengguna {$user->name} diperbarui.");
    }

    public function revokeDevice(Request $request, int $id, int $deviceId, RevokeDeviceUseCase $useCase): RedirectResponse
    {
        $device = Device::where('user_id', $id)->findOrFail($deviceId);
        $useCase->handle($device, $request->user());

        return back()->with('success', 'Perangkat dicabut.');
    }

    /** A deactivated user's already-issued mobile tokens must stop working immediately. */
    private function cutOffSessions(User $user): void
    {
        $user->tokens()->delete();
        RefreshToken::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nip' => $user->nip,
            'phone' => $user->phone,
            'role' => $user->getRoleNames()->first(),
            'position_id' => $user->position_id,
            'position' => $user->position?->name,
            'work_location_id' => $user->work_location_id,
            'work_location' => $user->workLocation?->name,
            'is_active' => $user->is_active,
        ];
    }

    /**
     * Audit values never include the password hash.
     *
     * @return array<string, mixed>
     */
    private function auditable(User $user): array
    {
        return $user->only(['name', 'email', 'nip', 'phone', 'position_id', 'work_location_id', 'is_active'])
            + ['role' => $user->getRoleNames()->first()];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(User $actor): array
    {
        return [
            'roles' => SaveUserRequest::assignableRoles($actor),
            'positions' => Position::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'work_locations' => WorkLocation::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }
}
