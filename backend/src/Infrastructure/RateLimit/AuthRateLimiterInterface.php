<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\RateLimit;

interface AuthRateLimiterInterface
{
    public function allow(string $clientKey): bool;
}
