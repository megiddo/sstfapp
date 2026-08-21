<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use InvalidArgumentException;

final class UsernameKey
{
    private function __construct(
        private readonly string $normalized,
    ) {
    }

    public static function fromUsername(string $username): self
    {
        $normalized = strtolower(trim($username));
        if ($normalized === '') {
            throw new InvalidArgumentException('Username cannot be empty');
        }
        if (strlen($normalized) > 64) {
            throw new InvalidArgumentException('Username is too long');
        }
        if (preg_match('/^[a-z0-9._@+-]+$/', $normalized) !== 1) {
            throw new InvalidArgumentException('Username contains invalid characters');
        }

        return new self($normalized);
    }

    public function normalized(): string
    {
        return $this->normalized;
    }
}
