<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use RuntimeException;

final class ScheduleNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('not_found');
    }
}
