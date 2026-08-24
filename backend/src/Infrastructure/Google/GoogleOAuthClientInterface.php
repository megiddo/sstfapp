<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Google;

use Sstf\Api\Domain\VerifiedGoogleUser;

interface GoogleOAuthClientInterface
{
    public function isConfigured(): bool;

    public function authorizationUrl(string $state): string;

    public function fetchUser(string $code): VerifiedGoogleUser;
}
