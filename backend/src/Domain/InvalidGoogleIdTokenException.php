<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use RuntimeException;

final class InvalidGoogleIdTokenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('invalid_token');
    }
}
