<?php

namespace Modules\Evidence\Domain\Exceptions;

class EvidenceIncompleteException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('This activity needs at least one capture session with both a location and a photo before it can be completed.');
    }
}
