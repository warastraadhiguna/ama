<?php

namespace Modules\Activities\Domain\Exceptions;

class PlanNotRealizableException extends \RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct("This plan cannot be realized: {$reason}");
    }
}
