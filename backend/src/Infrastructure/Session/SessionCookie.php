<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Session;

use InvalidArgumentException;

final class SessionCookie
{
    public function __construct(
        private readonly string $name,
        private readonly bool $secure,
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('Cookie name cannot be empty');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function header(string $value, int $maxAgeSeconds): string
    {
        if ($maxAgeSeconds < 0) {
            throw new InvalidArgumentException('Cookie Max-Age cannot be negative');
        }

        $parts = [
            $this->name . '=' . $value,
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
            'Max-Age=' . $maxAgeSeconds,
        ];
        if ($this->secure === true) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    public function expireHeader(): string
    {
        return $this->header('', 0);
    }
}
