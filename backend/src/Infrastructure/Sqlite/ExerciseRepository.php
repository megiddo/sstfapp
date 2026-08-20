<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Sqlite;

use PDO;
use PDOException;
use Sstf\Api\Domain\ClockInterface;
use Sstf\Api\Domain\DuplicateExerciseNameException;
use Sstf\Api\Domain\Exercise;

final class ExerciseRepository
{
    public function __construct(
        private readonly GlobalDb $global,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<Exercise>
     */
    public function search(?string $query): array
    {
        $pdo = $this->global->connect();
        if ($query === null || $query === '') {
            $stmt = $pdo->query(
                'SELECT id, name, muscle_group, equipment, notes
                 FROM exercises
                 ORDER BY name COLLATE NOCASE ASC',
            );

            return $this->mapRows($stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        $pattern = '%' . $this->escapeLike($query) . '%';
        $stmt = $pdo->prepare(
            'SELECT id, name, muscle_group, equipment, notes
             FROM exercises
             WHERE name LIKE :q ESCAPE :esc COLLATE NOCASE
                OR IFNULL(muscle_group, \'\') LIKE :q2 ESCAPE :esc2 COLLATE NOCASE
             ORDER BY name COLLATE NOCASE ASC',
        );
        $stmt->execute([
            'q' => $pattern,
            'q2' => $pattern,
            'esc' => '\\',
            'esc2' => '\\',
        ]);

        return $this->mapRows($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?Exercise
    {
        $pdo = $this->global->connect();
        $stmt = $pdo->prepare(
            'SELECT id, name, muscle_group, equipment, notes FROM exercises WHERE id = :id',
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return $this->mapRow($row);
    }

    public function create(string $name, ?string $muscleGroup, ?string $equipment, ?string $notes): Exercise
    {
        $pdo = $this->global->connect();
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('c');

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO exercises (name, muscle_group, equipment, notes, created_at, updated_at)
                 VALUES (:name, :muscle_group, :equipment, :notes, :created_at, :updated_at)',
            );
            $stmt->execute([
                'name' => $name,
                'muscle_group' => $muscleGroup,
                'equipment' => $equipment,
                'notes' => $notes,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (PDOException) {
            throw new DuplicateExerciseNameException();
        }

        $id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare(
            'SELECT id, name, muscle_group, equipment, notes FROM exercises WHERE id = :id',
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $this->mapRow($row);
    }

    /**
     * @param list<mixed> $rows
     * @return list<Exercise>
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
    private function mapRow(array $row): Exercise
    {
        return new Exercise(
            (int) $row['id'],
            (string) $row['name'],
            $this->nullableString($row['muscle_group'] ?? null),
            $this->nullableString($row['equipment'] ?? null),
            $this->nullableString($row['notes'] ?? null),
        );
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
