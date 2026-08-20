<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final readonly class TrainingSet
{
    /**
     * @param list<SetExercise> $exercises
     */
    public function __construct(
        public int $id,
        public int $scheduleId,
        public string $name,
        public int $dayOfWeek,
        public int $startMinutes,
        public int $sortOrder,
        public array $exercises,
    ) {
    }

    /**
     * @return array{
     *   id: int,
     *   schedule_id: int,
     *   name: string,
     *   day_of_week: int,
     *   start_minutes: int,
     *   sort_order: int,
     *   exercises: list<array{id: int, global_exercise_id: ?int, name: string, muscle_group: ?string, equipment: ?string, sort_order: int}>
     * }
     */
    public function toApi(): array
    {
        $exercises = [];
        foreach ($this->exercises as $exercise) {
            $exercises[] = $exercise->toApi();
        }

        return [
            'id' => $this->id,
            'schedule_id' => $this->scheduleId,
            'name' => $this->name,
            'day_of_week' => $this->dayOfWeek,
            'start_minutes' => $this->startMinutes,
            'sort_order' => $this->sortOrder,
            'exercises' => $exercises,
        ];
    }
}
