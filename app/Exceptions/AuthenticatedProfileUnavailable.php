<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AuthenticatedProfileUnavailable extends RuntimeException
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('The authenticated account profile could not be loaded.', previous: $previous);
    }
}
