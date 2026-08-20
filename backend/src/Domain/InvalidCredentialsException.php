<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use RuntimeException;

final class InvalidCredentialsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('invalid_credentials');
    }
}
