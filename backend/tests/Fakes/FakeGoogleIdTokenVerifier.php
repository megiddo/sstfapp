<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Fakes;

use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Domain\VerifiedGoogleUser;
use Sstf\Api\Infrastructure\Google\GoogleIdTokenVerifierInterface;

final class FakeGoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    /** @var array<string, VerifiedGoogleUser|\Throwable> */
    private array $outcomes = [];

    public function willVerify(string $idToken, VerifiedGoogleUser $user): void
    {
        $this->outcomes[$idToken] = $user;
    }

    public function willFail(string $idToken, \Throwable $error): void
    {
        $this->outcomes[$idToken] = $error;
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

    public function verify(string $idToken): VerifiedGoogleUser
    {
        if (!isset($this->outcomes[$idToken])) {
            throw new InvalidGoogleIdTokenException();
        }

        $outcome = $this->outcomes[$idToken];
        if ($outcome instanceof \Throwable) {
            throw $outcome;
        }

        return $outcome;
    }
}
