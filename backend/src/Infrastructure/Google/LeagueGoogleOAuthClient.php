<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Google;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Domain\VerifiedGoogleUser;
use Throwable;

final class LeagueGoogleOAuthClient implements GoogleOAuthClientInterface
{
    public function __construct(
        private readonly AbstractProvider $provider,
        private readonly string $clientId,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->clientId !== '';
    }

    public function authorizationUrl(string $state): string
    {
        if ($state === '' || !$this->isConfigured()) {
            throw new InvalidGoogleIdTokenException();
        }

        return $this->provider->getAuthorizationUrl([
            'state' => $state,
            'scope' => ['openid', 'email', 'profile'],
            'prompt' => 'select_account',
        ]);
    }

    public function fetchUser(string $code): VerifiedGoogleUser
    {
        if ($code === '' || !$this->isConfigured()) {
            throw new InvalidGoogleIdTokenException();
        }

        try {
            $token = $this->provider->getAccessToken('authorization_code', ['code' => $code]);
            $owner = $this->provider->getResourceOwner($token);
        } catch (IdentityProviderException | Throwable) {
            throw new InvalidGoogleIdTokenException();
        }

        $data = $owner->toArray();
        $email = $data['email'] ?? null;
        if (!is_string($email) || trim($email) === '') {
            throw new InvalidGoogleIdTokenException();
        }

        $subject = $owner->getId();
        if (!is_string($subject) || $subject === '') {
            $subject = $data['sub'] ?? $data['id'] ?? null;
        }
        if (!is_string($subject) || $subject === '') {
            throw new InvalidGoogleIdTokenException();
        }

        $verified = $data['email_verified'] ?? false;
        $emailVerified = $verified === true || $verified === 'true' || $verified === '1' || $verified === 1;

        $expires = $token->getExpires();
        $expiresAt = is_int($expires) ? $expires : time() + 3600;

        return new VerifiedGoogleUser(
            email: $email,
            emailVerified: $emailVerified,
            subject: $subject,
            audience: $this->clientId,
            issuer: 'https://accounts.google.com',
            expiresAt: $expiresAt,
        );
    }
}
