<?php

declare(strict_types=1);

namespace Sstf\Api\Services;

use Sstf\Api\Domain\Exercise;
use Sstf\Api\Domain\InvalidExerciseException;
use Sstf\Api\Infrastructure\Sqlite\ExerciseRepository;

final class ExerciseService
{
    public function __construct(
        private readonly ExerciseRepository $exercises,
    ) {
    }

    /**
     * @return list<Exercise>
     */
    public function list(?string $query): array
    {
        $normalized = $this->normalizeQuery($query);

        return $this->exercises->search($normalized);
    }

    public function create(string $name, ?string $muscleGroup, ?string $equipment, ?string $notes): Exercise
    {
        $trimmedName = trim($name);
        if ($trimmedName === '') {
            throw new InvalidExerciseException();
        }

        return $this->exercises->create(
            $trimmedName,
            $this->normalizeOptional($muscleGroup),
            $this->normalizeOptional($equipment),
            $this->normalizeOptional($notes),
        );
    }

    private function normalizeQuery(?string $query): ?string
    {
        if ($query === null) {
            return null;
        }

        $trimmed = trim($query);
        if ($trimmed === '') {
            return null;
        }

        return $trimmed;
    }

    private function normalizeOptional(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return $trimmed;
    }
}
