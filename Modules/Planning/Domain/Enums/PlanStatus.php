<?php

namespace Modules\Planning\Domain\Enums;

/**
 * State machine from docs section 13.3. REALIZED is reserved for the
 * Activities module (Milestone E) to set when a realization is linked to
 * this plan — it is never a legal target from the Planning API directly.
 */
enum PlanStatus: string
{
    case Planned = 'PLANNED';
    case Ready = 'READY';
    case Realized = 'REALIZED';
    case Cancelled = 'CANCELLED';

    /**
     * @return list<self>
     */
    public function apiTransitionsTo(): array
    {
        return match ($this) {
            self::Planned => [self::Ready, self::Cancelled],
            self::Ready => [self::Planned, self::Cancelled],
            self::Realized, self::Cancelled => [],
        };
    }

    public function canTransitionViaApiTo(self $target): bool
    {
        return in_array($target, $this->apiTransitionsTo(), true);
    }

    public function isEditable(): bool
    {
        return $this === self::Planned || $this === self::Ready;
    }
}
