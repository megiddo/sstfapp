<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Sqlite;

use InvalidArgumentException;
use PDO;

final class GlobalDb
{
    public function __construct(
        private readonly string $path,
        private readonly Migrator $migrator,
        private readonly string $migrationsDirectory,
    ) {
        if ($this->path === '') {
            throw new InvalidArgumentException('Global database path cannot be empty');
        }
        if ($this->migrationsDirectory === '') {
            throw new InvalidArgumentException('Global migrations directory cannot be empty');
        }
    }

    public function connect(): PDO
    {
        $pdo = SqliteConnection::open($this->path);
        $this->migrator->migrate($pdo, $this->migrationsDirectory);

        return $pdo;
    }

    public function path(): string
    {
        return $this->path;
    }
}
