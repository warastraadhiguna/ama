<?php

namespace Modules\Evidence\Domain\Exceptions;

class ActivityNotEditableException extends \RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct("Evidence cannot be added to this activity: {$reason}");
    }
}
