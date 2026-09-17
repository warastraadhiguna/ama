<?php

namespace Modules\Identity\Domain\Exceptions;

class InvalidCredentialsException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The provided credentials are incorrect.');
    }
}
