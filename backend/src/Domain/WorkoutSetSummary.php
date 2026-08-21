<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final readonly class WorkoutSetSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public int $dayOfWeek,
        public int $startMinutes,
        public int $exerciseCount,
        public bool $isClosest,
    ) {
    }

    /**
     * @return array{
     *   id: int,
     *   name: string,
     *   day_of_week: int,
     *   start_minutes: int,
     *   exercise_count: int,
     *   is_closest: bool
     * }
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'day_of_week' => $this->dayOfWeek,
            'start_minutes' => $this->startMinutes,
            'exercise_count' => $this->exerciseCount,
            'is_closest' => $this->isClosest,
        ];
    }
}
