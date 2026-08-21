<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\RateLimit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Infrastructure\RateLimit\MemoryAuthRateLimiter;
use Sstf\Api\Tests\Fakes\FakeClock;

#[CoversClass(MemoryAuthRateLimiter::class)]
final class MemoryAuthRateLimiterTest extends TestCase
{
    public function testAllowsUpToMaxThenDeniesUntilWindowExpires(): void
    {
        $clock = new FakeClock(1_000);
        $limiter = new MemoryAuthRateLimiter(2, 10, $clock);

        $this->assertTrue($limiter->allow('127.0.0.1'));
        $this->assertTrue($limiter->allow('127.0.0.1'));
        $this->assertFalse($limiter->allow('127.0.0.1'));
        $this->assertFalse($limiter->allow('127.0.0.1'));

        $this->assertTrue($limiter->allow('10.0.0.2'));

        $clock->setTimestamp(1_010);
        $this->assertTrue($limiter->allow('127.0.0.1'));
        $this->assertTrue($limiter->allow('127.0.0.1'));
        $this->assertFalse($limiter->allow('127.0.0.1'));
    }

    public function testZeroMaxOrWindowFailsClosed(): void
    {
        $clock = new FakeClock(50);
        $this->assertFalse((new MemoryAuthRateLimiter(0, 10, $clock))->allow('ip'));
        $this->assertFalse((new MemoryAuthRateLimiter(-1, 10, $clock))->allow('ip'));
        $this->assertFalse((new MemoryAuthRateLimiter(5, 0, $clock))->allow('ip'));
        $this->assertFalse((new MemoryAuthRateLimiter(5, -3, $clock))->allow('ip'));
        $one = new MemoryAuthRateLimiter(1, 1, $clock);
        $this->assertTrue($one->allow('ip'));
        $this->assertFalse($one->allow('ip'));
    }
}
