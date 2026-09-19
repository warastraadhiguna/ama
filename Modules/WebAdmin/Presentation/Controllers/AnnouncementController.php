<?php

namespace Modules\WebAdmin\Presentation\Controllers;

use App\Models\WorkLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Notifications\Application\UseCases\SendAnnouncementUseCase;
use Spatie\Permission\Models\Role;

/** docs section 28 "pengumuman admin", sent from Web Admin. */
class AnnouncementController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Announcements/Create', [
            'roles' => Role::orderBy('name')->pluck('name'),
            'workLocations' => WorkLocation::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, SendAnnouncementUseCase $useCase): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'work_location_id' => ['nullable', 'integer', 'exists:work_locations,id'],
        ]);

        $count = $useCase->handle(
            $request->user(),
            $data['title'],
            $data['body'],
            $data['role'] ?? null,
            isset($data['work_location_id']) ? (int) $data['work_location_id'] : null,
        );

        return back()->with('success', "Pengumuman dikirim ke {$count} pengguna.");
    }
}
