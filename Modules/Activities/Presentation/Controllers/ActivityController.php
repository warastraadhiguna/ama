<?php

namespace Modules\Activities\Presentation\Controllers;

use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Activities\Application\UseCases\CreateActivityUseCase;
use Modules\Activities\Domain\Exceptions\PlanNotRealizableException;
use Modules\Activities\Presentation\Requests\StoreActivityRequest;
use Modules\Activities\Presentation\Resources\ActivityResource;

class ActivityController extends Controller
{
    /**
     * Same visibility model as Plans (see PlanController): activities.view
     * is the monitoring permission (sees everyone's), activities.create
     * only sees what you created yourself.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Activity::query()->with(['activityType', 'products', 'creator']);

        if (! $user->can('activities.view')) {
            $query->where('creator_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return response()->json([
            'data' => ActivityResource::collection($query->latest()->get()),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $activity = Activity::with(['activityType', 'products', 'creator'])->findOrFail($id);

        if (! $user->can('activities.view') && $activity->creator_id !== $user->id) {
            abort(404);
        }

        return response()->json(['data' => new ActivityResource($activity)]);
    }

    public function store(StoreActivityRequest $request, CreateActivityUseCase $useCase): JsonResponse
    {
        try {
            $result = $useCase->handle($request->user(), $request->validated());
        } catch (PlanNotRealizableException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new ActivityResource($result['activity'])], $result['created'] ? 201 : 200);
    }
}
