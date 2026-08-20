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

        $google = $pdo->prepare('SELECT 1 FROM identities WHERE provider = :provider LIMIT 1');
        $google->execute(['provider' => 'google']);
        if ($google->fetchColumn() === false) {
            $insert = $pdo->prepare(
                'INSERT INTO identities (provider, provider_subject, created_at)
                 VALUES (:provider, :subject, :created_at)',
            );
            $insert->execute([
                'provider' => 'google',
                'subject' => $googleSubject,
                'created_at' => $now,
            ]);
        }

        $this->upsertIndex($key->hash(), $now);

        $loaded = $this->fetchAccount($pdo);
        if ($loaded === null) {
            throw new RuntimeException('Failed to load account after provision');
        }

        return $loaded;
    }

    public function loadAccount(string $emailHash): ?AccountSnapshot
    {
        if (!$this->users->exists($emailHash)) {
            return null;
        }

        $pdo = $this->users->open($emailHash);

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
