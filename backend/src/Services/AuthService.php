<?php

declare(strict_types=1);

namespace Sstf\Api\Services;

use InvalidArgumentException;
use RuntimeException;
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
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Infrastructure\Google\GoogleIdTokenVerifierInterface;
use Sstf\Api\Infrastructure\Session\SessionService;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;

final class AuthService
{
    /**
     * @param array<string, int> $hashOptions
     */
    public function __construct(
        private readonly GoogleIdTokenVerifierInterface $verifier,
        private readonly UserDirectory $users,
        private readonly SessionService $sessions,
        private readonly array $hashOptions = [],
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

    /**
     * @return array{account: AccountSnapshot, cookie: string}
     */
    public function signInWithPassword(string $email, string $password): array
    {
        try {
            $key = EmailKey::fromEmail($email);
        } catch (InvalidArgumentException) {
            throw new InvalidCredentialsException();
        }

        if (!$this->users->userFileExists($key->hash())) {
            throw new InvalidCredentialsException();
        }

        $stored = $this->users->passwordHash($key->hash());
        if ($stored === null || !password_verify($password, $stored)) {
            throw new InvalidCredentialsException();
        }

        $account = $this->users->loadAccount($key->hash());
        if ($account === null) {
            throw new InvalidCredentialsException();
        }

        return [
            'account' => $account,
            'cookie' => $this->sessions->create($key->hash()),
        ];
    }

    /**
     * @return array{account: AccountSnapshot, cookie: string}
     */
    public function registerWithPassword(string $email, string $password, ?string $timezone): array
    {
        if ($password === '') {
            throw new InvalidPasswordException();
        }

        try {
            $key = EmailKey::fromEmail($email);
        } catch (InvalidArgumentException) {
            throw new InvalidCredentialsException();
        }

        if ($this->users->userFileExists($key->hash())) {
            throw new InvalidCredentialsException();
        }

        $account = $this->users->provisionPasswordUser(
            $key,
            $email,
            $this->hashPassword($password),
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
            $account = $this->users->setPasswordHash($emailHash, $passwordHashToSet);
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
