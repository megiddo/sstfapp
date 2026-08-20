<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Sqlite;

use PDO;
use Sstf\Api\Domain\ClockInterface;
use Sstf\Api\Domain\Exercise;
use Sstf\Api\Domain\ExerciseLog;
use Sstf\Api\Domain\LogPrefill;

final class LogRepository
{
    public function __construct(
        private readonly UserDbFactory $users,
        private readonly ClockInterface $clock,
    ) {
    }

    public function latestForSetExercise(string $emailHash, int $setId, int $globalExerciseId): ?LogPrefill
    {
        $pdo = $this->users->open($emailHash);
        $stmt = $pdo->prepare(
            'SELECT weight, reps
             FROM logs
             WHERE set_id = :set_id AND global_exercise_id = :global_exercise_id
             ORDER BY logged_at DESC, id DESC
             LIMIT 1',
        );
        $stmt->execute([
            'set_id' => $setId,
            'global_exercise_id' => $globalExerciseId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $this->mapPrefill($row);
    }

    public function latestForExercise(string $emailHash, int $globalExerciseId): ?LogPrefill
    {
        $pdo = $this->users->open($emailHash);
        $stmt = $pdo->prepare(
            'SELECT weight, reps
             FROM logs
             WHERE global_exercise_id = :global_exercise_id
             ORDER BY logged_at DESC, id DESC
             LIMIT 1',
        );
        $stmt->execute([
            'global_exercise_id' => $globalExerciseId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $this->mapPrefill($row);
    }

    public function insert(
        string $emailHash,
        ?int $scheduleId,
        string $scheduleName,
        ?int $setId,
        string $setName,
        ?int $setDayOfWeek,
        ?int $setStartMinutes,
        ?int $globalExerciseId,
        string $exerciseName,
        ?string $muscleGroup,
        float $weight,
        string $weightUnit,
        int $reps,
        ?string $notes,
    ): ExerciseLog {
        $pdo = $this->users->open($emailHash);
        $loggedAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('c');
        $stmt = $pdo->prepare(
            'INSERT INTO logs (
                logged_at, schedule_id, schedule_name, set_id, set_name, set_day_of_week, set_start_minutes,
                global_exercise_id, exercise_name, muscle_group, weight, weight_unit, reps, notes
            ) VALUES (
                :logged_at, :schedule_id, :schedule_name, :set_id, :set_name, :set_day_of_week, :set_start_minutes,
                :global_exercise_id, :exercise_name, :muscle_group, :weight, :weight_unit, :reps, :notes
            )',
        );
        $stmt->execute([
            'logged_at' => $loggedAt,
            'schedule_id' => $scheduleId,
            'schedule_name' => $scheduleName,
            'set_id' => $setId,
            'set_name' => $setName,
            'set_day_of_week' => $setDayOfWeek,
            'set_start_minutes' => $setStartMinutes,
            'global_exercise_id' => $globalExerciseId,
            'exercise_name' => $exerciseName,
            'muscle_group' => $muscleGroup,
            'weight' => $weight,
            'weight_unit' => $weightUnit,
            'reps' => $reps,
            'notes' => $notes,
        ]);

        return $this->getById($emailHash, (int) $pdo->lastInsertId());
    }

    public function getById(string $emailHash, int $id): ExerciseLog
    {
        $pdo = $this->users->open($emailHash);
        $stmt = $pdo->prepare(
            'SELECT id, logged_at, schedule_id, schedule_name, set_id, set_name, set_day_of_week, set_start_minutes,
                    global_exercise_id, exercise_name, muscle_group, weight, weight_unit, reps, notes
             FROM logs
             WHERE id = :id',
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new \RuntimeException('Log row missing after insert');
        }

        return $this->mapLog($row);
    }

    /**
     * @return list<ExerciseLog>
     */
    public function listAll(string $emailHash, ?int $globalExerciseId = null): array
    {
        $pdo = $this->users->open($emailHash);
        $sql = 'SELECT id, logged_at, schedule_id, schedule_name, set_id, set_name, set_day_of_week, set_start_minutes,
                    global_exercise_id, exercise_name, muscle_group, weight, weight_unit, reps, notes
             FROM logs';
        if ($globalExerciseId === null) {
            $stmt = $pdo->query($sql . ' ORDER BY logged_at DESC, id DESC');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare($sql . ' WHERE global_exercise_id = :id ORDER BY logged_at DESC, id DESC');
            $stmt->execute(['id' => $globalExerciseId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $logs = [];
        foreach ($rows as $row) {
            $logs[] = $this->mapLog($row);
        }

        return $logs;
    }

    /**
     * @return array{recent: list<Exercise>, frequent: list<Exercise>}
     */
    public function suggested(string $emailHash): array
    {
        $pdo = $this->users->open($emailHash);

        return [
            'recent' => $this->suggestedRows(
                $pdo,
                'SELECT global_exercise_id, exercise_name, muscle_group
                 FROM logs
                 WHERE global_exercise_id IS NOT NULL
                 GROUP BY global_exercise_id
                 ORDER BY MAX(logged_at) DESC, MAX(id) DESC
                 LIMIT 8',
            ),
            'frequent' => $this->suggestedRows(
                $pdo,
                'SELECT global_exercise_id, exercise_name, muscle_group
                 FROM logs
                 WHERE global_exercise_id IS NOT NULL
                 GROUP BY global_exercise_id
                 ORDER BY COUNT(*) DESC, MAX(logged_at) DESC, MAX(id) DESC
                 LIMIT 8',
            ),
        ];
    }

    /**
     * @return list<Exercise>
     */
    private function suggestedRows(PDO $pdo, string $sql): array
    {
        $stmt = $pdo->query($sql);
        $exercises = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $exercises[] = new Exercise(
                (int) $row['global_exercise_id'],
                (string) $row['exercise_name'],
                $this->nullableString($row['muscle_group'] ?? null),
                null,
                null,
            );
        }

        return $exercises;
    }

    /**
     * @param array<mixed>|false $row
     */
    private function mapPrefill(array|false $row): ?LogPrefill
    {
        if ($row === false) {
            return null;
        }

        return new LogPrefill((float) $row['weight'], (int) $row['reps']);
    }

    /**
     * @param array<mixed> $row
     */
    private function mapLog(array $row): ExerciseLog
    {
        return new ExerciseLog(
            (int) $row['id'],
            (string) $row['logged_at'],
            $this->nullableInt($row['schedule_id'] ?? null),
            (string) $row['schedule_name'],
            $this->nullableInt($row['set_id'] ?? null),
            (string) $row['set_name'],
            $this->nullableInt($row['set_day_of_week'] ?? null),
            $this->nullableInt($row['set_start_minutes'] ?? null),
            $this->nullableInt($row['global_exercise_id'] ?? null),
            (string) $row['exercise_name'],
            $this->nullableString($row['muscle_group'] ?? null),
            (float) $row['weight'],
            (string) $row['weight_unit'],
            (int) $row['reps'],
            $this->nullableString($row['notes'] ?? null),
        );
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
