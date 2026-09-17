<?php

namespace Modules\Planning\Domain\Exceptions;

use Modules\Planning\Domain\Enums\PlanStatus;

class InvalidPlanStatusTransitionException extends \RuntimeException
{
    public function __construct(PlanStatus $from, PlanStatus $to)
    {
        parent::__construct("Cannot transition a plan from {$from->value} to {$to->value}.");
    }
}
