<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final readonly class HistoryFilters
{
    public function __construct(
        public ?string $from,
        public ?string $to,
        public ?int $exerciseId,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public static function fromQuery(array $query): self
    {
        return new self(
            self::optionalDay($query['from'] ?? null),
            self::optionalDay($query['to'] ?? null),
            self::optionalExerciseId($query['exercise_id'] ?? null),
        );
    }

    public function matchesDay(string $date): bool
    {
        if ($this->from !== null && $date < $this->from) {
            return false;
        }
        if ($this->to !== null && $date > $this->to) {
            return false;
        }

        return true;
    }

    private static function optionalDay(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidHistoryFilterException();
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match) !== 1) {
            throw new InvalidHistoryFilterException();
        }
        if (!checkdate((int) $match[2], (int) $match[3], (int) $match[1])) {
            throw new InvalidHistoryFilterException();
        }

        return $value;
    }

    private static function optionalExerciseId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            if ($value < 1) {
                throw new InvalidHistoryFilterException();
            }

            return $value;
        }
        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            throw new InvalidHistoryFilterException();
        }

        return (int) $value;
    }
}
