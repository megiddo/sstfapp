<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final readonly class WorkoutCurrent
{
    /**
     * @param list<array{
     *   id: int,
     *   global_exercise_id: ?int,
     *   name: string,
     *   muscle_group: ?string,
     *   equipment: ?string,
     *   last_weight: ?float,
     *   last_reps: ?int
     * }> $exercises
     */
    public function __construct(
        public ?Schedule $schedule,
        public ?TrainingSet $set,
        public string $weightUnit,
        public ?string $empty,
        public array $exercises,
        public ?int $closestSetId,
    ) {
    }

    /**
     * @return array{
     *   schedule: ?array{id: int, name: string, is_active: bool, set_count: int},
     *   set: ?array{
     *     id: int,
     *     schedule_id: int,
     *     name: string,
     *     day_of_week: int,
     *     start_minutes: int,
     *     sort_order: int,
     *     is_closest: bool
     *   },
     *   weight_unit: string,
     *   empty: ?string,
     *   closest_set_id: ?int,
     *   exercises: list<array{
     *     id: int,
     *     global_exercise_id: ?int,
     *     name: string,
     *     muscle_group: ?string,
     *     equipment: ?string,
     *     last_weight: ?float,
     *     last_reps: ?int
     *   }>
     * }
     */
    public function toApi(): array
    {
        $set = null;
        if ($this->set !== null) {
            $set = [
                'id' => $this->set->id,
                'schedule_id' => $this->set->scheduleId,
                'name' => $this->set->name,
                'day_of_week' => $this->set->dayOfWeek,
                'start_minutes' => $this->set->startMinutes,
                'sort_order' => $this->set->sortOrder,
                'is_closest' => $this->closestSetId !== null && $this->set->id === $this->closestSetId,
            ];
        }

        return [
            'schedule' => $this->schedule?->toApi(),
            'set' => $set,
            'weight_unit' => $this->weightUnit,
            'empty' => $this->empty,
            'closest_set_id' => $this->closestSetId,
            'exercises' => $this->exercises,
        ];
    }
}
