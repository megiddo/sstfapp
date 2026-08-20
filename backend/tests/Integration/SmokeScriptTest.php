<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class SmokeScriptTest extends TestCase
{
    public function testSmokeScriptExitsZeroInTesting(): void
    {
        $script = dirname(__DIR__, 2) . '/scripts/smoke.php';
        $this->assertFileExists($script);

        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
        $output = [];
        $code = 1;
        exec($cmd . ' 2>&1', $output, $code);
        $joined = implode("\n", $output);
        $this->assertSame(0, $code, $joined);
        $this->assertStringContainsString('SMOKE OK', $joined);
        $this->assertStringNotContainsString('SMOKE FAIL', $joined);
    }
}
