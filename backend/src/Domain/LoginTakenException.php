<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use RuntimeException;

final class LoginTakenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('login_taken');
    }
}
