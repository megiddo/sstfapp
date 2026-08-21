<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final readonly class ExerciseLog
{
    public function __construct(
        public int $id,
        public string $loggedAt,
        public ?int $scheduleId,
        public string $scheduleName,
        public ?int $setId,
        public string $setName,
        public ?int $setDayOfWeek,
        public ?int $setStartMinutes,
        public ?int $globalExerciseId,
        public string $exerciseName,
        public ?string $muscleGroup,
        public float $weight,
        public string $weightUnit,
        public int $reps,
        public ?string $notes,
    ) {
    }

    /**
     * @return array{
     *   id: int,
     *   logged_at: string,
     *   schedule_id: ?int,
     *   schedule_name: string,
     *   set_id: ?int,
     *   set_name: string,
     *   set_day_of_week: ?int,
     *   set_start_minutes: ?int,
     *   global_exercise_id: ?int,
     *   exercise_name: string,
     *   muscle_group: ?string,
     *   weight: float,
     *   weight_unit: string,
     *   reps: int,
     *   notes: ?string
     * }
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'logged_at' => $this->loggedAt,
            'schedule_id' => $this->scheduleId,
            'schedule_name' => $this->scheduleName,
            'set_id' => $this->setId,
            'set_name' => $this->setName,
            'set_day_of_week' => $this->setDayOfWeek,
            'set_start_minutes' => $this->setStartMinutes,
            'global_exercise_id' => $this->globalExerciseId,
            'exercise_name' => $this->exerciseName,
            'muscle_group' => $this->muscleGroup,
            'weight' => $this->weight,
            'weight_unit' => $this->weightUnit,
            'reps' => $this->reps,
            'notes' => $this->notes,
        ];
    }
}
