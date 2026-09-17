<?php

namespace Modules\Integrity\Domain\ValueObjects;

use Modules\Integrity\Domain\Enums\IntegrityStatus;

class PlayIntegrityVerificationResult
{
    /**
     * $status is null when $configured is false — there is nothing to
     * report, honestly, without a real verifier wired in.
     */
    public function __construct(
        public readonly bool $configured,
        public readonly ?IntegrityStatus $status,
        public readonly string $detail,
    ) {}
}
