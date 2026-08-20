<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\IanaTimezone;

#[CoversClass(IanaTimezone::class)]
final class IanaTimezoneTest extends TestCase
{
    public function testAcceptsIanaNames(): void
    {
        $this->assertSame('America/Chicago', IanaTimezone::resolve('America/Chicago'));
        $this->assertSame('UTC', IanaTimezone::resolve('UTC'));
        $this->assertSame('Europe/London', IanaTimezone::resolve('Europe/London'));
    }

    #[DataProvider('fallbackProvider')]
    public function testFallsBackToUtc(?string $name): void
    {
        $this->assertSame('UTC', IanaTimezone::resolve($name));
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function fallbackProvider(): array
    {
        return [
            'null' => [null],
            'empty' => [''],
            'whitespace' => ['  '],
            'garbage' => ['Not/A_Zone'],
            'wrong case' => ['america/chicago'],
            'offset' => ['GMT+1'],
        ];
    }

    public function testTrimsValidNames(): void
    {
        $this->assertSame('America/Chicago', IanaTimezone::resolve('  America/Chicago  '));
        $this->assertNotSame('UTC', IanaTimezone::resolve('  America/Chicago  '));
    }
}
