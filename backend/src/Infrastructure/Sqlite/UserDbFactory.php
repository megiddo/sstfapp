<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Sqlite;

use InvalidArgumentException;
use PDO;

final class UserDbFactory
{
    public const NAME_PATTERN = '/^[a-f0-9]{32}$/';

    public function __construct(
        private readonly string $usersDirectory,
        private readonly Migrator $migrator,
        private readonly string $migrationsDirectory,
    ) {
        if ($this->usersDirectory === '') {
            throw new InvalidArgumentException('Users directory cannot be empty');
        }
        if ($this->migrationsDirectory === '') {
            throw new InvalidArgumentException('User migrations directory cannot be empty');
        }
    }

    public function isValidName(string $name): bool
    {
        return preg_match(self::NAME_PATTERN, $name) === 1;
    }

    public function pathFor(string $name): string
    {
        if (!$this->isValidName($name)) {
            throw InvalidUserDbNameException::forName($name);
        }

        return $this->usersDirectory . '/' . $name . '.sqlite';
    }

    public function open(string $name): PDO
    {
        $path = $this->pathFor($name);
        $pdo = SqliteConnection::open($path);
        $this->migrator->migrate($pdo, $this->migrationsDirectory);

        return $pdo;
    }
}
