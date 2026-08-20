<?php

declare(strict_types=1);

namespace Sstf\Api\Services;

use RuntimeException;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Infrastructure\Sqlite\InvalidUserDbNameException;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;

final class ExportService
{
    public function __construct(
        private readonly UserDirectory $accounts,
        private readonly UserDbFactory $users,
    ) {
    }

    public function bytes(string $emailHash): string
    {
        try {
            $path = $this->users->pathFor($emailHash);
        } catch (InvalidUserDbNameException) {
            throw new UnauthenticatedException();
        }

        if ($this->accounts->loadAccount($emailHash) === null) {
            throw new UnauthenticatedException();
        }

        $pdo = $this->users->open($emailHash);
        $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');

        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException('Unable to read user database');
        }

        return $bytes;
    }
}
