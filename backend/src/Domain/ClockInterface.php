<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
