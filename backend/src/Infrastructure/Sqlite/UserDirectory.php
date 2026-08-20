<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Sqlite;

use PDO;
use RuntimeException;
use Sstf\Api\Domain\AccountSnapshot;
use Sstf\Api\Domain\ClockInterface;
use Sstf\Api\Domain\EmailKey;
use Sstf\Api\Domain\IanaTimezone;

final class UserDirectory
{
    public function __construct(
        private readonly UserDbFactory $users,
        private readonly GlobalDb $global,
        private readonly ClockInterface $clock,
    ) {
    }

    public function provisionGoogleUser(
        EmailKey $key,
        string $displayEmail,
        string $googleSubject,
        ?string $timezone,
    ): AccountSnapshot {
        $pdo = $this->users->open($key->hash());
        $existing = $this->fetchAccount($pdo);
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('c');

        if ($existing === null) {
            $resolvedTz = IanaTimezone::resolve($timezone);
            $stmt = $pdo->prepare(
                'INSERT INTO account (
                    id, email, email_normalized, password_hash, timezone, weight_unit, created_at, updated_at
                ) VALUES (
                    1, :email, :email_normalized, NULL, :timezone, :weight_unit, :created_at, :updated_at
                )',
            );
            $stmt->execute([
                'email' => $displayEmail,
                'email_normalized' => $key->normalized(),
                'timezone' => $resolvedTz,
                'weight_unit' => 'lb',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->ensureIdentity($pdo, 'google', $googleSubject, $now);
        $this->upsertIndex($key->hash(), $now);

        $loaded = $this->fetchAccount($pdo);
        if ($loaded === null) {
            throw new RuntimeException('Failed to load account after provision');
        }

        return $loaded;
    }

    public function provisionPasswordUser(
        EmailKey $key,
        string $displayEmail,
        string $passwordHash,
        ?string $timezone,
    ): AccountSnapshot {
        $pdo = $this->users->open($key->hash());
        $existing = $this->fetchAccount($pdo);
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('c');

        if ($existing === null) {
            $resolvedTz = IanaTimezone::resolve($timezone);
            $stmt = $pdo->prepare(
                'INSERT INTO account (
                    id, email, email_normalized, password_hash, timezone, weight_unit, created_at, updated_at
                ) VALUES (
                    1, :email, :email_normalized, :password_hash, :timezone, :weight_unit, :created_at, :updated_at
                )',
            );
            $stmt->execute([
                'email' => $displayEmail,
                'email_normalized' => $key->normalized(),
                'password_hash' => $passwordHash,
                'timezone' => $resolvedTz,
                'weight_unit' => 'lb',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE account SET password_hash = :password_hash, updated_at = :updated_at WHERE id = 1',
            );
            $stmt->execute([
                'password_hash' => $passwordHash,
                'updated_at' => $now,
            ]);
        }

        $this->ensureIdentity($pdo, 'password', $key->normalized(), $now);
        $this->upsertIndex($key->hash(), $now);

        $loaded = $this->fetchAccount($pdo);
        if ($loaded === null) {
            throw new RuntimeException('Failed to load account after password provision');
        }

        return $loaded;
    }

    public function userFileExists(string $emailHash): bool
    {
        return $this->users->exists($emailHash);
    }

    public function passwordHash(string $emailHash): ?string
    {
        if (!$this->users->exists($emailHash)) {
            return null;
        }

        $pdo = $this->users->open($emailHash);
        $row = $pdo->query('SELECT password_hash FROM account WHERE id = 1')->fetch();
        if (!is_array($row)) {
            return null;
        }

        $hash = $row['password_hash'] ?? null;
        if (!is_string($hash) || $hash === '') {
            return null;
        }

        return $hash;
    }

    public function setPasswordHash(string $emailHash, string $passwordHash): ?AccountSnapshot
    {
        if (!$this->users->exists($emailHash)) {
            return null;
        }

        $pdo = $this->users->open($emailHash);
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('c');
        $stmt = $pdo->prepare(
            'UPDATE account SET password_hash = :password_hash, updated_at = :updated_at WHERE id = 1',
        );
        $stmt->execute([
            'password_hash' => $passwordHash,
            'updated_at' => $now,
        ]);

        $email = $pdo->query('SELECT email_normalized FROM account WHERE id = 1')->fetchColumn();
        $subject = is_string($email) && $email !== '' ? $email : $emailHash;
        $this->ensureIdentity($pdo, 'password', $subject, $now);

        return $this->fetchAccount($pdo);
    }

    public function loadAccount(string $emailHash): ?AccountSnapshot
    {
        if (!$this->users->exists($emailHash)) {
            return null;
        }

        $pdo = $this->users->open($emailHash);

        return $this->fetchAccount($pdo);
    }

    public function updateAccount(string $emailHash, ?string $timezone, ?string $weightUnit): ?AccountSnapshot
    {
        if (!$this->users->exists($emailHash)) {
            return null;
        }

        $pdo = $this->users->open($emailHash);
        if ($timezone === null && $weightUnit === null) {
            return $this->fetchAccount($pdo);
        }

        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('c');
        if ($timezone !== null && $weightUnit !== null) {
            $stmt = $pdo->prepare(
                'UPDATE account SET timezone = :timezone, weight_unit = :weight_unit, updated_at = :updated_at WHERE id = 1',
            );
            $stmt->execute([
                'timezone' => $timezone,
                'weight_unit' => $weightUnit,
                'updated_at' => $now,
            ]);
        } elseif ($timezone !== null) {
            $stmt = $pdo->prepare(
                'UPDATE account SET timezone = :timezone, updated_at = :updated_at WHERE id = 1',
            );
            $stmt->execute([
                'timezone' => $timezone,
                'updated_at' => $now,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE account SET weight_unit = :weight_unit, updated_at = :updated_at WHERE id = 1',
            );
            $stmt->execute([
                'weight_unit' => $weightUnit,
                'updated_at' => $now,
            ]);
        }

        return $this->fetchAccount($pdo);
    }

    private function fetchAccount(PDO $pdo): ?AccountSnapshot
    {
        $row = $pdo->query('SELECT email, timezone, weight_unit FROM account WHERE id = 1')->fetch();
        if (!is_array($row)) {
            return null;
        }

        $email = $row['email'] ?? null;
        $timezone = $row['timezone'] ?? null;
        $weightUnit = $row['weight_unit'] ?? null;
        if (!is_string($email) || !is_string($timezone) || !is_string($weightUnit)) {
            return null;
        }

        $providerRows = $pdo->query('SELECT provider FROM identities ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
        $providers = [];
        foreach ($providerRows as $provider) {
            if (is_string($provider) && $provider !== '') {
                $providers[] = $provider;
            }
        }

        return new AccountSnapshot($email, $timezone, $weightUnit, $providers);
    }

    private function ensureIdentity(PDO $pdo, string $provider, string $subject, string $now): void
    {
        $existing = $pdo->prepare('SELECT 1 FROM identities WHERE provider = :provider LIMIT 1');
        $existing->execute(['provider' => $provider]);
        if ($existing->fetchColumn() !== false) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO identities (provider, provider_subject, created_at)
             VALUES (:provider, :subject, :created_at)',
        );
        $insert->execute([
            'provider' => $provider,
            'subject' => $subject,
            'created_at' => $now,
        ]);
    }

    private function upsertIndex(string $emailHash, string $createdAt): void
    {
        $pdo = $this->global->connect();
        $stmt = $pdo->prepare(
            'INSERT INTO user_index (email_hash, created_at)
             VALUES (:email_hash, :created_at)
             ON CONFLICT(email_hash) DO NOTHING',
        );
        $stmt->execute([
            'email_hash' => $emailHash,
            'created_at' => $createdAt,
        ]);
    }
}
