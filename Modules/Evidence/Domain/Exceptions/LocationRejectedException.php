<?php

namespace Modules\Evidence\Domain\Exceptions;

class LocationRejectedException extends \RuntimeException
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(public readonly array $reasons)
    {
        parent::__construct('This location submission was rejected: '.implode(', ', $reasons));
    }
}
