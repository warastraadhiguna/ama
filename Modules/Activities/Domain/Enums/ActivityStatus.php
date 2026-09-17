<?php

namespace Modules\Activities\Domain\Enums;

/**
 * docs section 23. Only DRAFT is reachable from Milestone E's endpoints —
 * SUBMITTED is set once evidence is completed (Milestone F's
 * /activities/{id}/complete), SYNCED reflects the offline-sync flow
 * (Milestone H), and VERIFIED/REJECTED come from an explicit review action
 * gated by activities.verify (not built yet, but the doc is explicit that
 * the design must allow it to be added later without a redesign — hence
 * defining the full enum now even though only DRAFT is produced today).
 */
enum ActivityStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Synced = 'SYNCED';
    case Verified = 'VERIFIED';
    case Rejected = 'REJECTED';
}
