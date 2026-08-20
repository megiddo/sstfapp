<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Sqlite;

use PDO;
use Sstf\Api\Domain\ClockInterface;
use Sstf\Api\Domain\Schedule;
use Sstf\Api\Domain\ScheduleNotFoundException;

final class ScheduleRepository
{
    public function __construct(
        private readonly UserDbFactory $users,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<Schedule>
     */
    public function listLive(string $emailHash): array
    {
        $pdo = $this->users->open($emailHash);
        $stmt = $pdo->query(
            'SELECT s.id, s.name, s.is_active,
                    (SELECT COUNT(*) FROM sets WHERE schedule_id = s.id) AS set_count
             FROM schedules s
             WHERE s.archived_at IS NULL
             ORDER BY s.id ASC',
        );

        return $this->mapRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getLive(string $emailHash, int $id): Schedule
    {
        $pdo = $this->users->open($emailHash);
        $stmt = $pdo->prepare(
            'SELECT s.id, s.name, s.is_active,
                    (SELECT COUNT(*) FROM sets WHERE schedule_id = s.id) AS set_count
             FROM schedules s
             WHERE s.id = :id AND s.archived_at IS NULL',
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new ScheduleNotFoundException();
        }

        return $this->mapRow($row);
    }

    public function create(string $emailHash, string $name): Schedule
    {
        $pdo = $this->users->open($emailHash);
        $now = $this->now();
        $pdo->beginTransaction();
        try {
            $activeCount = (int) $pdo->query(
                'SELECT COUNT(*) FROM schedules WHERE is_active = 1',
            )->fetchColumn();
            $isActive = $activeCount === 0 ? 1 : 0;

            $stmt = $pdo->prepare(
                'INSERT INTO schedules (name, is_active, created_at, updated_at, archived_at)
                 VALUES (:name, :is_active, :created_at, :updated_at, NULL)',
            );
            $stmt->execute([
                'name' => $name,
                'is_active' => $isActive,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $this->getLive($emailHash, $id);
    }

    public function rename(string $emailHash, int $id, string $name): Schedule
    {
        $this->getLive($emailHash, $id);
        $pdo = $this->users->open($emailHash);
        $stmt = $pdo->prepare(
            'UPDATE schedules SET name = :name, updated_at = :updated_at
             WHERE id = :id AND archived_at IS NULL',
        );
        $stmt->execute([
            'name' => $name,
            'updated_at' => $this->now(),
            'id' => $id,
        ]);

        return $this->getLive($emailHash, $id);
    }

    public function activate(string $emailHash, int $id): Schedule
    {
        $this->getLive($emailHash, $id);
        $pdo = $this->users->open($emailHash);
        $now = $this->now();
        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE schedules SET is_active = 0 WHERE is_active = 1');
            $stmt = $pdo->prepare(
                'UPDATE schedules
                 SET is_active = 1, updated_at = :updated_at
                 WHERE id = :id AND archived_at IS NULL',
            );
            $stmt->execute([
                'updated_at' => $now,
                'id' => $id,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $this->getLive($emailHash, $id);
    }

    public function archive(string $emailHash, int $id): void
    {
        $this->getLive($emailHash, $id);
        $pdo = $this->users->open($emailHash);
        $stmt = $pdo->prepare(
            'UPDATE schedules
             SET archived_at = :archived_at, is_active = 0, updated_at = :updated_at
             WHERE id = :id AND archived_at IS NULL',
        );
        $stmt->execute([
            'archived_at' => $this->now(),
            'updated_at' => $this->now(),
            'id' => $id,
        ]);
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('c');
    }

    /**
     * @param list<mixed> $rows
     * @return list<Schedule>
     */
    private function mapRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->mapRow($row);
        }

        return $out;
    }

    /**
     * @param array<mixed> $row
     */
    private function mapRow(array $row): Schedule
    {
        return new Schedule(
            (int) $row['id'],
            (string) $row['name'],
            (int) $row['is_active'] === 1,
            (int) $row['set_count'],
        );
    }
}
