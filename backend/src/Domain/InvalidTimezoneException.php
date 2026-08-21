<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use RuntimeException;

final class InvalidTimezoneException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('invalid_timezone');
    }
}
