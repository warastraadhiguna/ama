<?php

namespace Modules\Evidence\Application\UseCases;

use App\Models\Activity;
use Illuminate\Support\Facades\DB;
use Modules\Activities\Domain\Enums\ActivityStatus;
use Modules\Evidence\Domain\Exceptions\EvidenceIncompleteException;
use Modules\Evidence\Domain\Rules\EnsureActivityIsEditable;
use Modules\Integrity\Application\UseCases\EvaluateCaptureSessionWindowUseCase;

class CompleteActivityUseCase
{
    public function __construct(
        private readonly EnsureActivityIsEditable $ensureActivityIsEditable,
        private readonly EvaluateCaptureSessionWindowUseCase $evaluateCaptureSessionWindow,
    ) {}

    public function handle(Activity $activity): Activity
    {
        ($this->ensureActivityIsEditable)($activity);

        $completeSessions = $activity->captureSessions()
            ->whereHas('locations')
            ->whereHas('photos')
            ->get();

        if ($completeSessions->isEmpty()) {
            throw new EvidenceIncompleteException;
        }

        return DB::transaction(function () use ($activity, $completeSessions) {
            foreach ($completeSessions as $session) {
                $this->evaluateCaptureSessionWindow->handle($session);
            }

            $activity->captureSessions()->whereNull('submitted_at')->update(['submitted_at' => now()]);
            $activity->update(['status' => ActivityStatus::Submitted]);

            return $activity->fresh(['activityType', 'products', 'creator', 'captureSessions.locations', 'captureSessions.photos']);
        });
    }
}
