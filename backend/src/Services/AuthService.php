<?php

declare(strict_types=1);

namespace Sstf\Api\Services;

use InvalidArgumentException;
use Sstf\Api\Domain\AccountSnapshot;
use Sstf\Api\Domain\EmailKey;
use Sstf\Api\Domain\EmailUnverifiedException;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Infrastructure\Google\GoogleIdTokenVerifierInterface;
use Sstf\Api\Infrastructure\Session\SessionService;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;

final class AuthService
{
    public function __construct(
        private readonly GoogleIdTokenVerifierInterface $verifier,
        private readonly UserDirectory $users,
        private readonly SessionService $sessions,
    ) {
    }

    /**
     * @return array{account: AccountSnapshot, cookie: string}
     */
    public function signInWithGoogle(string $idToken, ?string $timezone): array
    {
        $verified = $this->verifier->verify($idToken);
        if ($verified->emailVerified !== true) {
            throw new EmailUnverifiedException();
        }

        try {
            $key = EmailKey::fromEmail($verified->email);
        } catch (InvalidArgumentException) {
            throw new InvalidGoogleIdTokenException();
        }

        $account = $this->users->provisionGoogleUser(
            $key,
            $verified->email,
            $verified->subject,
            $timezone,
        );

        return [
            'account' => $account,
            'cookie' => $this->sessions->create($key->hash()),
        ];
    }

    public function me(string $emailHash): AccountSnapshot
    {
        $account = $this->users->loadAccount($emailHash);
        if ($account === null) {
            throw new UnauthenticatedException();
        }

        return $account;
    }

    public function logout(?string $cookieValue): void
    {
        $this->sessions->destroy($cookieValue);
    }
}
