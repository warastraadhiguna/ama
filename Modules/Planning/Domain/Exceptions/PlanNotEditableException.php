<?php

namespace Modules\Planning\Domain\Exceptions;

class PlanNotEditableException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('This plan is realized or cancelled and can no longer be edited.');
    }
}
