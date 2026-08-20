<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use RuntimeException;

final class UnauthenticatedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('unauthenticated');
    }
}
