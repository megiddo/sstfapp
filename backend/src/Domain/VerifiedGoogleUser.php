<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final readonly class VerifiedGoogleUser
{
    public function __construct(
        public string $email,
        public bool $emailVerified,
        public string $subject,
        public string $audience,
        public string $issuer,
        public int $expiresAt,
    ) {
    }
}
