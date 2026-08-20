<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final readonly class Exercise
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $muscleGroup,
        public ?string $equipment,
        public ?string $notes,
    ) {
    }

    /**
     * @return array{id: int, name: string, muscle_group: ?string, equipment: ?string, notes: ?string}
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'muscle_group' => $this->muscleGroup,
            'equipment' => $this->equipment,
            'notes' => $this->notes,
        ];
    }
}
