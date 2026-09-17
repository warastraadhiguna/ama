<?php

namespace Modules\WebAdmin\Presentation\Controllers;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Product;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * docs section 29.4 (Activity Monitoring), 29.5 (Map) and the evidence
 * viewer / integrity review that Milestone I's own scope names explicitly
 * (docs section 48). One controller: the list (with map markers) and the
 * detail view are the same resource at two zoom levels, not two features.
 */
class ActivityMonitoringController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Activity::query()->with(['activityType', 'products', 'creator.workLocation', 'locations']);

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date('date'));
        }

        if ($request->filled('work_location_id')) {
            $query->whereHas(
                'creator',
                fn ($q) => $q->where('work_location_id', $request->integer('work_location_id')),
            );
        }

        if ($request->filled('creator_id')) {
            $query->where('creator_id', $request->integer('creator_id'));
        }

        if ($request->filled('activity_type_id')) {
            $query->where('activity_type_id', $request->integer('activity_type_id'));
        }

        if ($request->filled('product_id')) {
            $query->whereHas('products', fn ($q) => $q->where('products.id', $request->integer('product_id')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $activities = $query->latest()->paginate(20)->withQueryString();

        $activities->through(fn (Activity $activity) => $this->summarize($activity));

        return Inertia::render('Activities/Index', [
            'activities' => $activities,
            'filters' => $request->only(['date', 'work_location_id', 'creator_id', 'activity_type_id', 'product_id', 'status']),
            'filterOptions' => [
                'activityTypes' => ActivityType::orderBy('name')->get(['id', 'name']),
                'products' => Product::orderBy('name')->get(['id', 'name']),
                'workLocations' => WorkLocation::orderBy('name')->get(['id', 'name']),
                // User::role() throws if the role doesn't exist rather than
                // returning empty — guard it so this page doesn't 500 in an
                // environment where RolesAndPermissionsSeeder hasn't run.
                'agronomists' => Role::where('name', 'AGRONOMIST')->exists()
                    ? User::role('AGRONOMIST')->orderBy('name')->get(['id', 'name'])
                    : collect(),
            ],
        ]);
    }

    public function show(int $id): Response
    {
        $activity = Activity::with([
            'activityType', 'products', 'creator.position', 'creator.workLocation', 'plan',
            'captureSessions.locations', 'captureSessions.photos',
        ])->findOrFail($id);

        return Inertia::render('Activities/Show', [
            'activity' => [
                'id' => $activity->id,
                'status' => $activity->status->value,
                'location' => $activity->location,
                'notes' => $activity->notes,
                'created_at' => $activity->created_at->toIso8601String(),
                'activity_type' => $activity->activityType->name,
                'products' => $activity->products->pluck('name'),
                'creator' => [
                    'name' => $activity->creator->name,
                    'position' => $activity->creator->position?->name,
                    'work_location' => $activity->creator->workLocation?->name,
                ],
                'plan_id' => $activity->plan?->id,
                'capture_sessions' => $activity->captureSessions->map(fn ($session) => [
                    'id' => $session->id,
                    'started_at' => $session->started_at->toIso8601String(),
                    'submitted_at' => $session->submitted_at?->toIso8601String(),
                    'locations' => $session->locations->map(fn ($location) => [
                        'id' => $location->id,
                        'latitude' => (float) $location->latitude,
                        'longitude' => (float) $location->longitude,
                        'accuracy' => $location->accuracy,
                        'is_mock_location' => $location->is_mock_location,
                        'integrity_status' => $location->integrity_status->value,
                        'anomaly_reasons' => $location->anomaly_reasons,
                        'captured_at_device' => $location->captured_at_device->toIso8601String(),
                    ]),
                    'photos' => $session->photos->map(fn ($photo) => [
                        'id' => $photo->id,
                        'url' => Storage::disk('s3_public')->temporaryUrl($photo->storage_path, now()->addMinutes(10)),
                        'sha256_hash' => $photo->sha256_hash,
                        'file_size' => $photo->file_size,
                        'integrity_status' => $photo->integrity_status,
                        'captured_at_device' => $photo->captured_at_device->toIso8601String(),
                    ]),
                ]),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Activity $activity): array
    {
        $firstLocation = $activity->locations->first();

        return [
            'id' => $activity->id,
            'status' => $activity->status->value,
            'location' => $activity->location,
            'created_at' => $activity->created_at->toIso8601String(),
            'activity_type' => $activity->activityType->name,
            'products' => $activity->products->pluck('name'),
            'creator_name' => $activity->creator->name,
            'work_location' => $activity->creator->workLocation?->name,
            'integrity_status' => $firstLocation?->integrity_status?->value,
            'coordinates' => $firstLocation ? [
                'latitude' => (float) $firstLocation->latitude,
                'longitude' => (float) $firstLocation->longitude,
            ] : null,
        ];
    }
}
