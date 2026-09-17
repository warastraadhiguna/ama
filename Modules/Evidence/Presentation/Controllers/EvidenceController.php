<?php

namespace Modules\Evidence\Presentation\Controllers;

use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Activities\Presentation\Resources\ActivityResource;
use Modules\Evidence\Application\UseCases\CompleteActivityUseCase;
use Modules\Evidence\Application\UseCases\SubmitLocationUseCase;
use Modules\Evidence\Application\UseCases\UploadPhotoUseCase;
use Modules\Evidence\Domain\Exceptions\ActivityNotEditableException;
use Modules\Evidence\Domain\Exceptions\EvidenceIncompleteException;
use Modules\Evidence\Presentation\Requests\SubmitLocationRequest;
use Modules\Evidence\Presentation\Requests\UploadPhotoRequest;

class EvidenceController extends Controller
{
    public function submitLocation(SubmitLocationRequest $request, int $activityId, SubmitLocationUseCase $useCase): JsonResponse
    {
        $activity = $this->findOwnActivity($request, $activityId);

        try {
            $location = $useCase->handle($activity, $request->user(), $this->currentDeviceTokenName($request), $request->validated());
        } catch (ActivityNotEditableException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'id' => $location->id,
            'capture_session_id' => $location->capture_session_id,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'accuracy' => $location->accuracy,
        ]], 201);
    }

    public function uploadPhoto(UploadPhotoRequest $request, int $activityId, UploadPhotoUseCase $useCase): JsonResponse
    {
        $activity = $this->findOwnActivity($request, $activityId);

        try {
            $photo = $useCase->handle(
                $activity,
                $request->user(),
                $this->currentDeviceTokenName($request),
                $request->file('photo'),
                $request->validated(),
            );
        } catch (ActivityNotEditableException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => [
            'id' => $photo->id,
            'capture_session_id' => $photo->capture_session_id,
            'sha256_hash' => $photo->sha256_hash,
            'file_size' => $photo->file_size,
            'integrity_status' => $photo->integrity_status,
        ]], 201);
    }

    public function complete(Request $request, int $activityId, CompleteActivityUseCase $useCase): JsonResponse
    {
        $activity = $this->findOwnActivity($request, $activityId);

        try {
            $activity = $useCase->handle($activity);
        } catch (ActivityNotEditableException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (EvidenceIncompleteException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new ActivityResource($activity)]);
    }

    private function findOwnActivity(Request $request, int $id): Activity
    {
        $activity = Activity::findOrFail($id);

        if ($activity->creator_id !== $request->user()->id) {
            abort(403, 'You may only add evidence to your own activities.');
        }

        return $activity;
    }

    private function currentDeviceTokenName(Request $request): ?string
    {
        return $request->user()->currentAccessToken()?->name;
    }
}
