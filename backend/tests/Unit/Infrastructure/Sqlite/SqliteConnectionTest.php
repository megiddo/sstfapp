<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\Sqlite;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Infrastructure\Sqlite\SqliteConnection;

#[CoversClass(SqliteConnection::class)]
final class SqliteConnectionTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-sqlite-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testOpenCreatesNestedDirectoryAndFile(): void
    {
        $path = $this->tmp . '/a/b/c.sqlite';
        $pdo = SqliteConnection::open($path);

        $this->assertFileExists($path);
        $this->assertSame('wal', strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn()));
        $this->assertSame(1, (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn());
        $this->assertSame(SqliteConnection::FILE_MODE, fileperms($path) & 0777);
        $this->assertSame(\PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(\PDO::ATTR_ERRMODE));
        $this->assertSame(\PDO::FETCH_ASSOC, $pdo->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    #[DataProvider('invalidPathProvider')]
    public function testRejectsInvalidPaths(string $path, string $messageSnippet): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($messageSnippet);
        SqliteConnection::open($path);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidPathProvider(): array
    {
        return [
            'empty' => ['', 'non-empty filesystem path'],
            'memory' => [':memory:', 'non-empty filesystem path'],
            'bare filename' => ['global.sqlite', 'must include a directory'],
        ];
    }

    public function testReopenExistingFileKeepsWal(): void
    {
        $path = $this->tmp . '/db.sqlite';
        SqliteConnection::open($path);
        $pdo = SqliteConnection::open($path);
        $this->assertSame('wal', strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn()));
        $this->assertSame(1, (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn());
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            if (is_dir($full)) {
                $this->deleteTree($full);
            } else {
                unlink($full);
            }
        }
        rmdir($path);
    }
}
