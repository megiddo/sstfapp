<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Sqlite;

use InvalidArgumentException;

final class InvalidUserDbNameException extends InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self(
            'User database name must be exactly 32 lowercase hexadecimal characters; got: ' . $name,
        );
    }
}
