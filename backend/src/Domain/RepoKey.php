<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final class RepoKey
{
    private function __construct(
        private readonly string $normalized,
        private readonly string $hash,
    ) {
    }

    public static function google(string $email): self
    {
        $key = EmailKey::fromEmail($email);

        return new self($key->normalized(), $key->hash());
    }

    public static function password(string $username): self
    {
        $normalized = UsernameKey::fromUsername($username)->normalized();

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
