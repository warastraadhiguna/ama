<?php

namespace Modules\Integrity\Domain\ValueObjects;

use Modules\Integrity\Domain\Enums\IntegrityStatus;

class LocationEvaluationResult
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public readonly IntegrityStatus $status,
        public readonly array $reasons,
    ) {}
}
