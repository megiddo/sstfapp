<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Services\HealthService;

#[CoversClass(HealthService::class)]
final class HealthServiceTest extends TestCase
{
    public function testStatusReturnsOkPayload(): void
    {
        $service = new HealthService();
        $payload = $service->status();

        $this->assertSame(['ok' => true], $payload);
        $this->assertArrayHasKey('ok', $payload);
        $this->assertTrue($payload['ok']);
        $this->assertNotFalse($payload['ok']);
        $this->assertTrue($service->isHealthy());
    }

    public function testIsHealthyRequiresOkTrue(): void
    {
        $service = new HealthService();
        $this->assertTrue($service->isHealthy());
        $this->assertSame(true, $service->status()['ok']);
        $this->assertNotSame(false, $service->status()['ok']);
        $this->assertNotSame(0, $service->status()['ok']);
        $this->assertNotSame('true', $service->status()['ok']);
    }
}
