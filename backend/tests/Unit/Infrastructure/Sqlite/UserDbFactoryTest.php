<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\Sqlite;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Infrastructure\Sqlite\InvalidUserDbNameException;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\SqliteConnection;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;

#[CoversClass(UserDbFactory::class)]
#[CoversClass(InvalidUserDbNameException::class)]
#[CoversClass(Migrator::class)]
#[CoversClass(SqliteConnection::class)]
final class UserDbFactoryTest extends TestCase
{
    private const VALID = '0123456789abcdef0123456789abcdef';

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-userdb-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/users', 0700, true);
        mkdir($this->tmp . '/migs', 0700, true);
        file_put_contents(
            $this->tmp . '/migs/001_init.sql',
            'CREATE TABLE IF NOT EXISTS schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT NULL);',
        );
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testOpensValidLowercaseHexNameAndMigrates(): void
    {
        $factory = $this->factory();
        $this->assertTrue($factory->isValidName(self::VALID));
        $this->assertSame(1, preg_match(UserDbFactory::NAME_PATTERN, self::VALID));

        $expected = $this->tmp . '/users/' . self::VALID . '.sqlite';
        $this->assertSame($expected, $factory->pathFor(self::VALID));
        $this->assertFileDoesNotExist($expected);

        $pdo = $factory->open(self::VALID);
        $this->assertFileExists($expected);
        $this->assertSame(['001'], $pdo->query('SELECT version FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN));

        $mode = $pdo->query('PRAGMA journal_mode')->fetchColumn();
        $this->assertSame('wal', strtolower((string) $mode));
        $this->assertSame(1, (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn());
        $this->assertSame(SqliteConnection::FILE_MODE, fileperms($expected) & 0777);
    }

    public function testDoesNotCreateFileForRejectedNames(): void
    {
        $factory = $this->factory();
        $before = glob($this->tmp . '/users/*') ?: [];

        try {
            $factory->open('../' . self::VALID);
            $this->fail('Expected invalid name to throw');
        } catch (InvalidUserDbNameException) {
        }

        $after = glob($this->tmp . '/users/*') ?: [];
        $this->assertSame($before, $after);
        $this->assertFileDoesNotExist($this->tmp . '/users/' . self::VALID . '.sqlite');
    }

    #[DataProvider('invalidNameProvider')]
    public function testRejectsInvalidNames(string $name): void
    {
        $factory = $this->factory();
        $this->assertFalse($factory->isValidName($name));
        $this->assertNotSame(1, preg_match(UserDbFactory::NAME_PATTERN, $name));

        try {
            $factory->pathFor($name);
            $this->fail('pathFor should reject: ' . $name);
        } catch (InvalidUserDbNameException $e) {
            $this->assertStringStartsWith(
                'User database name must be exactly 32 lowercase hexadecimal characters; got: ',
                $e->getMessage(),
            );
            if ($name !== '') {
                $this->assertStringEndsWith($name, $e->getMessage());
            }
        }

        $this->expectException(InvalidUserDbNameException::class);
        $factory->open($name);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidNameProvider(): array
    {
        $hex = '0123456789abcdef0123456789abcdef';

        return [
            'uppercase' => ['0123456789ABCDEF0123456789ABCDEF'],
            'mixed case' => ['0123456789abcdeF0123456789abcdef'],
            'too short' => ['0123456789abcdef0123456789abcde'],
            'too long' => [$hex . 'a'],
            'empty' => [''],
            'parent directory' => ['../' . $hex],
            'leading slash' => ['/' . $hex],
            'nested path' => ['users/' . $hex],
            'dot prefix' => ['./' . $hex],
            'sqlite suffix' => [$hex . '.sqlite'],
            'extra suffix' => [$hex . 'x'],
            'prefix junk' => ['x' . $hex],
            'non hex' => ['gggggggggggggggggggggggggggggggg'],
            'spaces' => ['0123456789abcdef 123456789abcdef'],
            'hyphen' => ['0123456789abcdef-123456789abcdef'],
        ];
    }

    public function testEmptyUsersDirectoryRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Users directory cannot be empty');
        new UserDbFactory('', new Migrator(), $this->tmp . '/migs');
    }

    public function testEmptyMigrationsDirectoryRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User migrations directory cannot be empty');
        new UserDbFactory($this->tmp . '/users', new Migrator(), '');
    }

    public function testExceptionFactoryMethod(): void
    {
        $e = InvalidUserDbNameException::forName('NOPE');
        $this->assertInstanceOf(InvalidUserDbNameException::class, $e);
        $this->assertInstanceOf(InvalidArgumentException::class, $e);
        $this->assertStringContainsString('NOPE', $e->getMessage());
    }

    private function factory(): UserDbFactory
    {
        return new UserDbFactory($this->tmp . '/users', new Migrator(), $this->tmp . '/migs');
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
