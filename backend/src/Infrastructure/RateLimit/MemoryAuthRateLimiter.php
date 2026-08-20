<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\RateLimit;

use Sstf\Api\Domain\ClockInterface;

final class MemoryAuthRateLimiter implements AuthRateLimiterInterface
{
    /** @var array<string, list<int>> */
    private array $hits = [];

    public function __construct(
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
        private readonly ClockInterface $clock,
    ) {
    }

    public function allow(string $clientKey): bool
    {
        if ($this->maxAttempts < 1 || $this->windowSeconds < 1) {
            return false;
        }

        $now = $this->clock->now()->getTimestamp();
        $windowStart = $now - $this->windowSeconds;
        $times = $this->hits[$clientKey] ?? [];
        $kept = [];
        foreach ($times as $timestamp) {
            if ($timestamp > $windowStart) {
                $kept[] = $timestamp;
            }
        }

        if (count($kept) >= $this->maxAttempts) {
            $this->hits[$clientKey] = $kept;

            return false;
        }

        $kept[] = $now;
        $this->hits[$clientKey] = $kept;

        return true;
    }
}
