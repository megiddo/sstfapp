<?php

declare(strict_types=1);

namespace Sstf\Api\Services;

use InvalidArgumentException;
use RuntimeException;
use Sstf\Api\Domain\AccountExistsException;
use Sstf\Api\Domain\AccountSnapshot;
use Sstf\Api\Domain\EmailKey;
use Sstf\Api\Domain\EmailUnverifiedException;
use Sstf\Api\Domain\IanaTimezone;
use Sstf\Api\Domain\InvalidCredentialsException;
use Sstf\Api\Domain\InvalidCurrentPasswordException;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Domain\InvalidPasswordException;
use Sstf\Api\Domain\InvalidTimezoneException;
use Sstf\Api\Domain\InvalidWeightUnitException;
use Sstf\Api\Domain\LoginTakenException;
use Sstf\Api\Domain\RepoKey;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Domain\UsernameKey;
use Sstf\Api\Domain\VerifiedGoogleUser;
use Sstf\Api\Infrastructure\Session\SessionService;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;

final class AuthService
{
    /**
     * @param array<string, int> $hashOptions
     */
    public function __construct(
        private readonly UserDirectory $users,
        private readonly SessionService $sessions,
        private readonly array $hashOptions = [],
    ) {
    }

    /**
     * @return array{account: AccountSnapshot, cookie: string}
     */
    public function signInWithGoogle(VerifiedGoogleUser $verified, ?string $timezone): array
    {
        if ($verified->emailVerified !== true) {
            throw new EmailUnverifiedException();
        }

        try {
            $email = EmailKey::fromEmail($verified->email);
        } catch (InvalidArgumentException) {
            throw new InvalidGoogleIdTokenException();
        }

        $mapped = $this->users->repoHashForLogin('google', $email->normalized());
        $repoHash = $mapped ?? RepoKey::google($email->normalized())->hash();
        $account = $this->users->provisionGoogleUser(
            $repoHash,
            $verified->email,
            $email->normalized(),
            $verified->subject,
            $timezone,
        );

        return [
            'account' => $account,
            'cookie' => $this->sessions->create($repoHash),
        ];
    }

    /**
     * @return array{account: AccountSnapshot, cookie: string}
     */
    public function signInWithPassword(string $username, string $password): array
    {
        try {
            $login = UsernameKey::fromUsername($username);
        } catch (InvalidArgumentException) {
            throw new InvalidCredentialsException();
        }

        $repoHash = $this->users->repoHashForLogin('password', $login->normalized());
        if ($repoHash === null) {
            throw new InvalidCredentialsException();
        }

        $stored = $this->users->passwordHash($repoHash);
        if ($stored === null || !password_verify($password, $stored)) {
            throw new InvalidCredentialsException();
        }

        $account = $this->users->loadAccount($repoHash);
        if ($account === null) {
            throw new InvalidCredentialsException();
        }

        return [
            'account' => $account,
            'cookie' => $this->sessions->create($repoHash),
        ];
    }

    /**
     * @return array{account: AccountSnapshot, cookie: string}
     */
    public function registerWithPassword(string $username, string $password, ?string $timezone): array
    {
        if ($password === '') {
            throw new InvalidPasswordException();
        }

        try {
            $login = UsernameKey::fromUsername($username);
        } catch (InvalidArgumentException) {
            throw new InvalidCredentialsException();
        }

        if ($this->users->repoHashForLogin('password', $login->normalized()) !== null) {
            throw new AccountExistsException();
        }

        $repoHash = RepoKey::password($login->normalized())->hash();
        try {
            $account = $this->users->provisionPasswordUser(
                $repoHash,
                $username,
                $login->normalized(),
                $this->hashPassword($password),
                $timezone,
            );
        } catch (LoginTakenException) {
            throw new AccountExistsException();
        }

        return [
            'account' => $account,
            'cookie' => $this->sessions->create($repoHash),
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

    public function updateMe(
        string $emailHash,
        ?string $timezone,
        ?string $weightUnit,
        ?string $password = null,
        ?string $currentPassword = null,
    ): AccountSnapshot {
        $resolvedTimezone = null;
        if ($timezone !== null) {
            $resolvedTimezone = IanaTimezone::tryParse($timezone);
            if ($resolvedTimezone === null) {
                throw new InvalidTimezoneException();
            }
        }

        $resolvedUnit = null;
        if ($weightUnit !== null) {
            if ($weightUnit !== 'lb' && $weightUnit !== 'kg') {
                throw new InvalidWeightUnitException();
            }
            $resolvedUnit = $weightUnit;
        }

        $passwordHashToSet = null;
        if ($password !== null) {
            if ($password === '') {
                throw new InvalidPasswordException();
            }
            $existingHash = $this->users->passwordHash($emailHash);
            if ($existingHash !== null) {
                if ($currentPassword === null || $currentPassword === '' || !password_verify($currentPassword, $existingHash)) {
                    throw new InvalidCurrentPasswordException();
                }
            }
            $passwordHashToSet = $this->hashPassword($password);
        }

        $account = $this->users->updateAccount($emailHash, $resolvedTimezone, $resolvedUnit);
        if ($account === null) {
            throw new UnauthenticatedException();
        }

        if ($passwordHashToSet !== null) {
            try {
                $account = $this->users->setPasswordHash($emailHash, $passwordHashToSet);
            } catch (LoginTakenException) {
                throw new AccountExistsException();
            }
            if ($account === null) {
                throw new UnauthenticatedException();
            }
        }

        return $account;
    }

    public function logout(?string $cookieValue): void
    {
        $this->sessions->destroy($cookieValue);
    }

    private function hashPassword(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID, $this->hashOptions);
        if (!is_string($hash) || $hash === '') {
            throw new RuntimeException('Unable to hash password');
        }

        return $hash;
    }
}
