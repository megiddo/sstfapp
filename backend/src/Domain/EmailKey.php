<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use InvalidArgumentException;

final class EmailKey
{
    private function __construct(
        private readonly string $normalized,
        private readonly string $hash,
    ) {
    }

    public static function fromEmail(string $email): self
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            throw new InvalidArgumentException('Email cannot be empty');
        }
        if (!str_contains($normalized, '@')) {
            throw new InvalidArgumentException('Email must contain @');
        }

        return new self($normalized, md5($normalized));
    }

    public function normalized(): string
    {
        return $this->normalized;
    }

    public function hash(): string
    {
        return $this->hash;
    }
}
