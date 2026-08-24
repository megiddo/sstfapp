<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Google;

use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Domain\VerifiedGoogleUser;

final class UnconfiguredGoogleOAuthClient implements GoogleOAuthClientInterface
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function authorizationUrl(string $state): string
    {
        throw new InvalidGoogleIdTokenException();
    }

    public function fetchUser(string $code): VerifiedGoogleUser
    {
        throw new InvalidGoogleIdTokenException();
    }
}
