<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Sqlite;

use PDO;
use Sstf\Api\Domain\ClockInterface;
use Sstf\Api\Domain\ScheduleNotFoundException;
use Sstf\Api\Domain\SetExercise;
use Sstf\Api\Domain\SetNotFoundException;
use Sstf\Api\Domain\TrainingSet;

final class SetRepository
{
    public function __construct(
        private readonly UserDbFactory $users,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<TrainingSet>
     */
    public function listForSchedule(string $emailHash, int $scheduleId): array
    {
        $this->requireLiveSchedule($emailHash, $scheduleId);
        $pdo = $this->users->open($emailHash);
        $stmt = $pdo->prepare(
            'SELECT id, schedule_id, name, day_of_week, start_minutes, sort_order
             FROM sets
             WHERE schedule_id = :schedule_id
             ORDER BY day_of_week ASC, start_minutes ASC, sort_order ASC, id ASC',
        );
        $stmt->execute(['schedule_id' => $scheduleId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->hydrateSets($pdo, $rows);
    }

    public function getLive(string $emailHash, int $id): TrainingSet
    {
        $pdo = $this->users->open($emailHash);
        $stmt = $pdo->prepare(
            'SELECT sets.id, sets.schedule_id, sets.name, sets.day_of_week, sets.start_minutes, sets.sort_order
             FROM sets
             INNER JOIN schedules ON schedules.id = sets.schedule_id
             WHERE sets.id = :id AND schedules.archived_at IS NULL',
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new SetNotFoundException();
        }

        return $this->hydrateSets($pdo, [$row])[0];
    }

    public function create(
        string $emailHash,
        int $scheduleId,
        string $name,
        int $dayOfWeek,
        int $startMinutes,
        int $sortOrder,
    ): TrainingSet {
        $this->requireLiveSchedule($emailHash, $scheduleId);
        $pdo = $this->users->open($emailHash);
        $now = $this->now();
        $stmt = $pdo->prepare(
            'INSERT INTO sets (schedule_id, name, day_of_week, start_minutes, sort_order, created_at, updated_at)
             VALUES (:schedule_id, :name, :day_of_week, :start_minutes, :sort_order, :created_at, :updated_at)',
        );
        $stmt->execute([
            'schedule_id' => $scheduleId,
            'name' => $name,
            'day_of_week' => $dayOfWeek,
            'start_minutes' => $startMinutes,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->getLive($emailHash, (int) $pdo->lastInsertId());
    }

    public function update(
        string $emailHash,
        int $id,
        string $name,
        int $dayOfWeek,
        int $startMinutes,
        int $sortOrder,
    ): TrainingSet {
        $this->getLive($emailHash, $id);
        $pdo = $this->users->open($emailHash);
        $stmt = $pdo->prepare(
            'UPDATE sets
             SET name = :name, day_of_week = :day_of_week, start_minutes = :start_minutes,
                 sort_order = :sort_order, updated_at = :updated_at
             WHERE id = :id',
        );
        $stmt->execute([
            'name' => $name,
            'day_of_week' => $dayOfWeek,
            'start_minutes' => $startMinutes,
            'sort_order' => $sortOrder,
            'updated_at' => $this->now(),
            'id' => $id,
        ]);

        return $this->getLive($emailHash, $id);
    }

    public function delete(string $emailHash, int $id): void
    {
        $this->getLive($emailHash, $id);
        $pdo = $this->users->open($emailHash);
        $stmt = $pdo->prepare('DELETE FROM sets WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @param list<array{global_exercise_id: int, name: string, muscle_group: ?string, equipment: ?string, sort_order: int}> $rows
     */
    public function replaceExercises(string $emailHash, int $setId, array $rows): TrainingSet
    {
        $this->getLive($emailHash, $setId);
        $pdo = $this->users->open($emailHash);
        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare('DELETE FROM set_exercises WHERE set_id = :set_id');
            $delete->execute(['set_id' => $setId]);
            $insert = $pdo->prepare(
                'INSERT INTO set_exercises (set_id, global_exercise_id, name, muscle_group, equipment, sort_order)
                 VALUES (:set_id, :global_exercise_id, :name, :muscle_group, :equipment, :sort_order)',
            );
            foreach ($rows as $row) {
                $insert->execute([
                    'set_id' => $setId,
                    'global_exercise_id' => $row['global_exercise_id'],
                    'name' => $row['name'],
                    'muscle_group' => $row['muscle_group'],
                    'equipment' => $row['equipment'],
                    'sort_order' => $row['sort_order'],
                ]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return $this->getLive($emailHash, $setId);
    }

    private function requireLiveSchedule(string $emailHash, int $scheduleId): void
    {
        $pdo = $this->users->open($emailHash);
        $stmt = $pdo->prepare(
            'SELECT 1 FROM schedules WHERE id = :id AND archived_at IS NULL',
        );
        $stmt->execute(['id' => $scheduleId]);
        if ($stmt->fetchColumn() === false) {
            throw new ScheduleNotFoundException();
        }
    }

    /**
     * @param list<array<mixed>> $rows
     * @return list<TrainingSet>
     */
    private function hydrateSets(PDO $pdo, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int) $row['id'];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT id, set_id, global_exercise_id, name, muscle_group, equipment, sort_order
             FROM set_exercises
             WHERE set_id IN ({$placeholders})
             ORDER BY sort_order ASC, id ASC",
        );
        $stmt->execute($ids);
        $bySet = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $exerciseRow) {
            $setId = (int) $exerciseRow['set_id'];
            $bySet[$setId][] = $this->mapExercise($exerciseRow);
        }

        $sets = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $sets[] = new TrainingSet(
                $id,
                (int) $row['schedule_id'],
                (string) $row['name'],
                (int) $row['day_of_week'],
                (int) $row['start_minutes'],
                (int) $row['sort_order'],
                $bySet[$id] ?? [],
            );
        }

        return $sets;
    }

    /**
     * @param array<mixed> $row
     */
    private function mapExercise(array $row): SetExercise
    {
        $globalId = $row['global_exercise_id'];

        return new SetExercise(
            (int) $row['id'],
            $globalId === null ? null : (int) $globalId,
            (string) $row['name'],
            $this->nullableString($row['muscle_group'] ?? null),
            $this->nullableString($row['equipment'] ?? null),
            (int) $row['sort_order'],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('c');
    }
}
