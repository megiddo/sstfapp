<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Fakes;

use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Domain\VerifiedGoogleUser;
use Sstf\Api\Infrastructure\Google\GoogleOAuthClientInterface;

final class FakeGoogleOAuthClient implements GoogleOAuthClientInterface
{
    public bool $configured = true;

    public bool $failAuthorization = false;

    public string $authorizeBaseUrl = 'https://accounts.google.com/o/oauth2/v2/auth';

    /** @var array<string, VerifiedGoogleUser|\Throwable> */
    private array $codes = [];

    public function willReturnUser(string $code, VerifiedGoogleUser $user): void
    {
        $this->codes[$code] = $user;
    }

    public function willFail(string $code, \Throwable $error): void
    {
        $this->codes[$code] = $error;
    }

    public static function user(
        string $email,
        bool $emailVerified = true,
        string $subject = 'google-subject-1',
    ): VerifiedGoogleUser {
        return new VerifiedGoogleUser(
            email: $email,
            emailVerified: $emailVerified,
            subject: $subject,
            audience: 'test-google-client-id.apps.googleusercontent.com',
            issuer: 'https://accounts.google.com',
            expiresAt: time() + 3600,
        );
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function authorizationUrl(string $state): string
    {
        if ($this->configured !== true || $this->failAuthorization) {
            throw new InvalidGoogleIdTokenException();
        }

        return $this->authorizeBaseUrl . '?state=' . rawurlencode($state);
    }

    public function fetchUser(string $code): VerifiedGoogleUser
    {
        if (!isset($this->codes[$code])) {
            throw new InvalidGoogleIdTokenException();
        }

        $outcome = $this->codes[$code];
        if ($outcome instanceof \Throwable) {
            throw $outcome;
        }

        return $outcome;
    }
}
