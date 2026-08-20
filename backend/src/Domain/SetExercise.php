<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final readonly class SetExercise
{
    public function __construct(
        public int $id,
        public ?int $globalExerciseId,
        public string $name,
        public ?string $muscleGroup,
        public ?string $equipment,
        public int $sortOrder,
    ) {
    }

    /**
     * @return array{id: int, global_exercise_id: ?int, name: string, muscle_group: ?string, equipment: ?string, sort_order: int}
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'global_exercise_id' => $this->globalExerciseId,
            'name' => $this->name,
            'muscle_group' => $this->muscleGroup,
            'equipment' => $this->equipment,
            'sort_order' => $this->sortOrder,
        ];
    }
}
