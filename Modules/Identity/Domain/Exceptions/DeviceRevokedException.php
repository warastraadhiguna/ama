<?php

namespace Modules\Identity\Domain\Exceptions;

class DeviceRevokedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('This device has been revoked and can no longer authenticate.');
    }
}
