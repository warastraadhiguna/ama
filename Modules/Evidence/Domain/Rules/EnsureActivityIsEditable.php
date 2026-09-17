<?php

namespace Modules\Evidence\Domain\Rules;

use App\Models\Activity;
use Modules\Activities\Domain\Enums\ActivityStatus;
use Modules\Evidence\Domain\Exceptions\ActivityNotEditableException;

class EnsureActivityIsEditable
{
    /**
     * Ownership is checked separately, in the controller (consistent with
     * Planning's PlanController: ownership -> 403, state -> 422) — this
     * rule only covers the state check.
     */
    public function __invoke(Activity $activity): void
    {
        if ($activity->status !== ActivityStatus::Draft) {
            throw new ActivityNotEditableException('it is no longer a draft.');
        }
    }
}
