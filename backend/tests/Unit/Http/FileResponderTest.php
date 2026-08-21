<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Http\FileResponder;

#[CoversClass(FileResponder::class)]
final class FileResponderTest extends TestCase
{
    public function testDownloadSetsAttachmentHeadersWithoutEmail(): void
    {
        $bytes = "SQLite format 3\0payload";
        $response = FileResponder::download(
            $bytes,
            FileResponder::SQLITE_FILENAME,
            FileResponder::SQLITE_TYPE,
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(FileResponder::SQLITE_TYPE, $response->getHeaderLine('Content-Type'));
        $this->assertSame(
            'attachment; filename="sstf-data.sqlite"',
            $response->getHeaderLine('Content-Disposition'),
        );
        $this->assertSame((string) strlen($bytes), $response->getHeaderLine('Content-Length'));
        $this->assertSame($bytes, (string) $response->getBody());
        $this->assertStringNotContainsString('@', $response->getHeaderLine('Content-Disposition'));
        $this->assertStringNotContainsString('password', strtolower($response->getHeaderLine('Content-Disposition')));
        $this->assertNotSame('application/json', $response->getHeaderLine('Content-Type'));
    }
}
