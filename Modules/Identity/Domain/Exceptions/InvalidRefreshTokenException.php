<?php

namespace Modules\Identity\Domain\Exceptions;

class InvalidRefreshTokenException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The refresh token is invalid, expired, or has been revoked.');
    }
}
