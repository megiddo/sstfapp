<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Fakes;

use RuntimeException;
use Sstf\Api\Infrastructure\RateLimit\AuthRateLimiterInterface;

final class ThrowingAuthRateLimiter implements AuthRateLimiterInterface
{
    public function allow(string $clientKey): bool
    {
        throw new RuntimeException('rate limiter store failed');
    }
}
