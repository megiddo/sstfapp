<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\AccountSnapshot;
use Sstf\Api\Domain\SystemClock;

#[CoversClass(AccountSnapshot::class)]
#[CoversClass(SystemClock::class)]
final class AccountSnapshotAndClockTest extends TestCase
{
    public function testToApiListsProviderNamesOnly(): void
    {
        $snapshot = new AccountSnapshot('a@b.com', 'America/Chicago', 'lb', ['google', 'password']);
        $api = $snapshot->toApi();

        $this->assertSame('a@b.com', $api['email']);
        $this->assertSame('America/Chicago', $api['timezone']);
        $this->assertSame('lb', $api['weight_unit']);
        $this->assertSame(
            [['provider' => 'google'], ['provider' => 'password']],
            $api['identities'],
        );
        $this->assertArrayNotHasKey('password_hash', $api);
        $this->assertArrayNotHasKey('provider_subject', $api['identities'][0]);
        $this->assertSame(['google', 'password'], $snapshot->providers);
    }

    public function testToApiEmptyIdentities(): void
    {
        $api = (new AccountSnapshot('x@y.z', 'UTC', 'kg', []))->toApi();
        $this->assertSame([], $api['identities']);
        $this->assertSame('kg', $api['weight_unit']);
    }

    public function testSystemClockIsUtcNow(): void
    {
        $before = time();
        $now = (new SystemClock())->now();
        $after = time();

        $this->assertSame('UTC', $now->getTimezone()->getName());
        $this->assertGreaterThanOrEqual($before, $now->getTimestamp());
        $this->assertLessThanOrEqual($after + 1, $now->getTimestamp());
        $this->assertNotSame('America/Chicago', $now->getTimezone()->getName());
    }
}
