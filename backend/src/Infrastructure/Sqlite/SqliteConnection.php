<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Sqlite;

use InvalidArgumentException;
use PDO;

final class SqliteConnection
{
    public const FILE_MODE = 0600;
    public const DIR_MODE = 0700;

    public static function open(string $path): PDO
    {
        if ($path === '' || $path === ':memory:') {
            throw new InvalidArgumentException('SQLite path must be a non-empty filesystem path');
        }

        $directory = dirname($path);
        if ($directory === '' || $directory === '.') {
            throw new InvalidArgumentException('SQLite path must include a directory');
        }

        if (!is_dir($directory) && !mkdir($directory, self::DIR_MODE, true) && !is_dir($directory)) {
            throw new InvalidArgumentException('Unable to create SQLite directory: ' . $directory);
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec('PRAGMA foreign_keys=ON');

        if (is_file($path)) {
            chmod($path, self::FILE_MODE);
        }

        return $pdo;
    }
}
