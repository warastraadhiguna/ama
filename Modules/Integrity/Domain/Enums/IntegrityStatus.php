<?php

namespace Modules\Integrity\Domain\Enums;

/**
 * docs section 19.7 (Layer 6): a status, not a boolean fake=true/false.
 * Shared by devices (Play Integrity, Milestone B) and activity_locations
 * (this milestone's evaluation).
 */
enum IntegrityStatus: string
{
    case Trusted = 'TRUSTED';
    case Suspicious = 'SUSPICIOUS';
    case Rejected = 'REJECTED';
}
