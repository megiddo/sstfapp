<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\Sqlite;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\SqliteConnection;

#[CoversClass(Migrator::class)]
#[CoversClass(SqliteConnection::class)]
final class MigratorTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-migrator-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testAppliesNumberedSqlAndRecordsVersions(): void
    {
        $dir = $this->tmp . '/migs';
        mkdir($dir);
        file_put_contents(
            $dir . '/001_widgets.sql',
            'CREATE TABLE widgets (id INTEGER PRIMARY KEY, name TEXT NOT NULL);',
        );
        file_put_contents(
            $dir . '/002_gadget.sql',
            'CREATE TABLE gadgets (id INTEGER PRIMARY KEY);',
        );

        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        $migrator = new Migrator();
        $migrator->migrate($pdo, $dir);

        $versions = $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['001', '002'], $versions);
        $this->assertNotFalse($pdo->query('SELECT 1 FROM widgets'));
        $this->assertNotFalse($pdo->query('SELECT 1 FROM gadgets'));

        $appliedAt = $pdo->query('SELECT applied_at FROM schema_migrations WHERE version = "001"')->fetchColumn();
        $this->assertIsString($appliedAt);
        $this->assertStringContainsString('T', $appliedAt);
        $this->assertNotSame('', $appliedAt);
    }

    public function testMigrateIsIdempotent(): void
    {
        $dir = $this->tmp . '/migs';
        mkdir($dir);
        file_put_contents($dir . '/001_once.sql', 'CREATE TABLE once (id INTEGER PRIMARY KEY);');

        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        $migrator = new Migrator();
        $migrator->migrate($pdo, $dir);
        $migrator->migrate($pdo, $dir);
        $migrator->migrate($pdo, $dir);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
        $this->assertSame(1, $count);
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM sqlite_master WHERE name = "once"')->fetchColumn());
    }

    public function testFailedMigrationRollsBackAndDoesNotRecordVersion(): void
    {
        $dir = $this->tmp . '/migs';
        mkdir($dir);
        file_put_contents($dir . '/001_ok.sql', 'CREATE TABLE keep_me (id INTEGER PRIMARY KEY);');

        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        $migrator = new Migrator();
        $migrator->migrate($pdo, $dir);

        file_put_contents(
            $dir . '/002_bad.sql',
            "CREATE TABLE should_not_exist (id INTEGER PRIMARY KEY);\nTHIS IS NOT SQL;",
        );

        try {
            $migrator->migrate($pdo, $dir);
            $this->fail('Expected invalid SQL to throw');
        } catch (\Throwable $e) {
            $this->assertNotSame('', $e->getMessage());
        }

        $versions = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['001'], $versions);
        $this->assertSame(
            0,
            (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'should_not_exist'")->fetchColumn(),
        );
        $this->assertSame(
            1,
            (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'keep_me'")->fetchColumn(),
        );
    }

    public function testAppliesInNumericOrderNotLexicographic(): void
    {
        $dir = $this->tmp . '/migs';
        mkdir($dir);
        file_put_contents($dir . '/10_second.sql', 'CREATE TABLE second_tbl (id INTEGER PRIMARY KEY);');
        file_put_contents($dir . '/2_first.sql', 'CREATE TABLE first_tbl (id INTEGER PRIMARY KEY);');

        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        (new Migrator())->migrate($pdo, $dir);

        $versions = $pdo->query('SELECT version FROM schema_migrations ORDER BY rowid ASC')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['2', '10'], $versions);
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'first_tbl'")->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'second_tbl'")->fetchColumn());
    }

    public function testSkipsNonNumberedSqlFiles(): void
    {
        $dir = $this->tmp . '/migs';
        mkdir($dir);
        file_put_contents($dir . '/README.sql', 'CREATE TABLE skipped (id INTEGER PRIMARY KEY);');
        file_put_contents($dir . '/001.sql', 'CREATE TABLE also_skipped (id INTEGER PRIMARY KEY);');
        file_put_contents($dir . '/001_ok.sql', 'CREATE TABLE kept (id INTEGER PRIMARY KEY);');

        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        (new Migrator())->migrate($pdo, $dir);

        $this->assertSame(['001'], $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'skipped'")->fetchColumn());
        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'also_skipped'")->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'kept'")->fetchColumn());
    }

    public function testPrefixAndSuffixAroundNumberedNameAreSkipped(): void
    {
        $dir = $this->tmp . '/migs';
        mkdir($dir);
        file_put_contents($dir . '/x001_ok.sql', 'CREATE TABLE prefix_skip (id INTEGER PRIMARY KEY);');
        file_put_contents($dir . '/001_ok.sqlx', 'CREATE TABLE suffix_skip (id INTEGER PRIMARY KEY);');
        file_put_contents($dir . '/001_ok.sql', 'CREATE TABLE kept (id INTEGER PRIMARY KEY);');

        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        (new Migrator())->migrate($pdo, $dir);

        $this->assertSame(['001'], $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'prefix_skip'")->fetchColumn());
        $this->assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'suffix_skip'")->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'kept'")->fetchColumn());
    }

    public function testLaterPendingFilesStillApplyAfterAnAlreadyAppliedVersion(): void
    {
        $dir = $this->tmp . '/migs';
        mkdir($dir);
        file_put_contents($dir . '/001_a.sql', 'CREATE TABLE a (id INTEGER PRIMARY KEY);');

        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        $migrator = new Migrator();
        $migrator->migrate($pdo, $dir);

        file_put_contents($dir . '/002_b.sql', 'CREATE TABLE b (id INTEGER PRIMARY KEY);');
        file_put_contents($dir . '/003_c.sql', 'CREATE TABLE c (id INTEGER PRIMARY KEY);');
        $migrator->migrate($pdo, $dir);

        $this->assertSame(
            ['001', '002', '003'],
            $pdo->query('SELECT version FROM schema_migrations ORDER BY rowid ASC')->fetchAll(PDO::FETCH_COLUMN),
        );
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'b'")->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'c'")->fetchColumn());
    }

    public function testDuplicateVersionThrows(): void
    {
        $dir = $this->tmp . '/migs';
        mkdir($dir);
        file_put_contents($dir . '/001_a.sql', 'CREATE TABLE a (id INTEGER PRIMARY KEY);');
        file_put_contents($dir . '/001_b.sql', 'CREATE TABLE b (id INTEGER PRIMARY KEY);');

        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate migration version: 001');
        (new Migrator())->migrate($pdo, $dir);
    }

    public function testEmptyDirectoryStillCreatesSchemaMigrations(): void
    {
        $dir = $this->tmp . '/empty';
        mkdir($dir);

        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        (new Migrator())->migrate($pdo, $dir);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
        $this->assertSame(0, $count);
        $this->assertSame(
            1,
            (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE name = 'schema_migrations'")->fetchColumn(),
        );
    }

    public function testEmptySqlFileStillRecordsVersion(): void
    {
        $dir = $this->tmp . '/migs';
        mkdir($dir);
        file_put_contents($dir . '/001_empty.sql', "   \n\t  ");

        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        (new Migrator())->migrate($pdo, $dir);

        $this->assertSame(['001'], $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testMissingDirectoryThrows(): void
    {
        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        $missing = $this->tmp . '/nope';
        try {
            (new Migrator())->migrate($pdo, $missing);
            $this->fail('Expected missing directory to throw');
        } catch (InvalidArgumentException $e) {
            $this->assertStringStartsWith('Migration directory does not exist: ', $e->getMessage());
            $this->assertStringEndsWith($missing, $e->getMessage());
            $this->assertNotSame($missing . 'Migration directory does not exist: ', $e->getMessage());
        }
    }

    public function testEmptyDirectoryPathThrows(): void
    {
        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        $this->expectException(InvalidArgumentException::class);
        (new Migrator())->migrate($pdo, '');
    }

    #[DataProvider('whitespaceDirectoryProvider')]
    public function testBlankishDirectoryThrows(string $directory): void
    {
        $pdo = SqliteConnection::open($this->tmp . '/db.sqlite');
        $this->expectException(InvalidArgumentException::class);
        (new Migrator())->migrate($pdo, $directory);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function whitespaceDirectoryProvider(): array
    {
        return [
            'spaces' => ['   '],
        ];
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
