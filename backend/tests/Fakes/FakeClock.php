<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Fakes;

use DateTimeImmutable;
use Sstf\Api\Domain\ClockInterface;

final class FakeClock implements ClockInterface
{
    public function __construct(
        private int $timestamp,
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@' . $this->timestamp);
    }

    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }
}
