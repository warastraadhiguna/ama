<?php

namespace Modules\Planning\Presentation\Controllers;

use App\Models\ActivityPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Planning\Application\UseCases\CreatePlanUseCase;
use Modules\Planning\Application\UseCases\UpdatePlanUseCase;
use Modules\Planning\Domain\Exceptions\InvalidPlanStatusTransitionException;
use Modules\Planning\Domain\Exceptions\PlanNotEditableException;
use Modules\Planning\Presentation\Requests\StorePlanRequest;
use Modules\Planning\Presentation\Requests\UpdatePlanRequest;
use Modules\Planning\Presentation\Resources\PlanResource;

class PlanController extends Controller
{
    /**
     * `plans.view` sees every plan (ADMIN/MANAGER/SUPERVISOR/SUPER_ADMIN
     * monitoring); a user without it only sees plans they created
     * themselves (docs OPEN QUESTION #9 — "can a user see another user's
     * activity" — is unresolved for Activities too; Planning uses this as
     * its default until the Product Owner says otherwise).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ActivityPlan::query()->with(['activityType', 'products', 'creator']);

        if (! $user->can('plans.view')) {
            $query->where('creator_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        return response()->json([
            'data' => PlanResource::collection($query->latest('planned_date')->get()),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $plan = $this->findAccessiblePlan($request, $id);

        return response()->json(['data' => new PlanResource($plan)]);
    }

    public function store(StorePlanRequest $request, CreatePlanUseCase $useCase): JsonResponse
    {
        $result = $useCase->handle($request->user(), $request->validated());

        // 200 (not 201) when a retried idempotency_key returned the
        // original plan rather than creating a new one — the client should
        // be able to tell "already done" apart from "just created".
        return response()->json(['data' => new PlanResource($result['plan'])], $result['created'] ? 201 : 200);
    }

    public function update(UpdatePlanRequest $request, int $id, UpdatePlanUseCase $useCase): JsonResponse
    {
        $plan = $this->findOwnPlan($request, $id);

        try {
            $plan = $useCase->handle($plan, $request->validated());
        } catch (PlanNotEditableException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InvalidPlanStatusTransitionException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new PlanResource($plan)]);
    }

    private function findAccessiblePlan(Request $request, int $id): ActivityPlan
    {
        $user = $request->user();
        $plan = ActivityPlan::with(['activityType', 'products', 'creator'])->findOrFail($id);

        if (! $user->can('plans.view') && $plan->creator_id !== $user->id) {
            abort(404);
        }

        return $plan;
    }

    /**
     * Unlike viewing, editing a plan always requires being its creator —
     * plans.view (monitoring) does not grant write access (see docs
     * section 44 Q9/Q11, unresolved; this is the conservative default).
     */
    private function findOwnPlan(Request $request, int $id): ActivityPlan
    {
        $plan = ActivityPlan::findOrFail($id);

        if ($plan->creator_id !== $request->user()->id) {
            abort(403, 'You may only edit your own plans.');
        }

        return $plan;
    }
}
