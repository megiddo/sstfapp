<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\Sqlite;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\SqliteConnection;

#[CoversClass(GlobalDb::class)]
#[CoversClass(Migrator::class)]
#[CoversClass(SqliteConnection::class)]
final class GlobalDbTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-global-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testConnectCreatesFileEnablesWalAndForeignKeysAndMigrates(): void
    {
        $migrations = $this->tmp . '/migs';
        mkdir($migrations);
        file_put_contents(
            $migrations . '/001_init.sql',
            'CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL);',
        );

        $path = $this->tmp . '/nested/global.sqlite';
        $this->assertFileDoesNotExist($path);

        $db = new GlobalDb($path, new Migrator(), $migrations);
        $this->assertSame($path, $db->path());

        $pdo = $db->connect();
        $this->assertFileExists($path);
        $this->assertSame(SqliteConnection::FILE_MODE, fileperms($path) & 0777);

        $mode = $pdo->query('PRAGMA journal_mode')->fetchColumn();
        $this->assertIsString($mode);
        $this->assertSame('wal', strtolower($mode));
        $this->assertNotSame('delete', strtolower((string) $mode));

        $fk = $pdo->query('PRAGMA foreign_keys')->fetchColumn();
        $this->assertSame(1, (int) $fk);
        $this->assertNotSame(0, (int) $fk);

        $versions = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['001'], $versions);

        $row = $pdo->query('SELECT version, applied_at FROM schema_migrations')->fetch();
        $this->assertIsArray($row);
        $this->assertArrayHasKey('version', $row);
        $this->assertArrayNotHasKey(0, $row);
    }

    public function testConnectIsIdempotentWhenFileAlreadyExists(): void
    {
        $migrations = $this->tmp . '/migs';
        mkdir($migrations);
        file_put_contents($migrations . '/001_init.sql', 'CREATE TABLE t (id INTEGER PRIMARY KEY);');

        $path = $this->tmp . '/global.sqlite';
        $db = new GlobalDb($path, new Migrator(), $migrations);
        $db->connect();
        $pdo = $db->connect();

        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());
    }

    public function testEmptyPathIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Global database path cannot be empty');
        new GlobalDb('', new Migrator(), $this->tmp);
    }

    public function testEmptyMigrationsDirectoryIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Global migrations directory cannot be empty');
        new GlobalDb($this->tmp . '/g.sqlite', new Migrator(), '');
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
