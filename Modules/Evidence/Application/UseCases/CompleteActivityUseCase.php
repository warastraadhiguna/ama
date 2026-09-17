<?php

namespace Modules\Evidence\Application\UseCases;

use App\Models\Activity;
use Illuminate\Support\Facades\DB;
use Modules\Activities\Domain\Enums\ActivityStatus;
use Modules\Evidence\Domain\Exceptions\EvidenceIncompleteException;
use Modules\Evidence\Domain\Rules\EnsureActivityIsEditable;

class CompleteActivityUseCase
{
    public function __construct(
        private readonly EnsureActivityIsEditable $ensureActivityIsEditable,
    ) {}

    public function handle(Activity $activity): Activity
    {
        ($this->ensureActivityIsEditable)($activity);

        $hasCompleteSession = $activity->captureSessions()
            ->whereHas('locations')
            ->whereHas('photos')
            ->exists();

        if (! $hasCompleteSession) {
            throw new EvidenceIncompleteException;
        }

        return DB::transaction(function () use ($activity) {
            $activity->captureSessions()->whereNull('submitted_at')->update(['submitted_at' => now()]);
            $activity->update(['status' => ActivityStatus::Submitted]);

            return $activity->fresh(['activityType', 'products', 'creator', 'captureSessions.locations', 'captureSessions.photos']);
        });
    }
}
