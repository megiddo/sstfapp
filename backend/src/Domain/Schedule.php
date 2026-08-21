<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final readonly class Schedule
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $isActive,
        public int $setCount,
    ) {
    }

    /**
     * @return array{id: int, name: string, is_active: bool, set_count: int}
     */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->isActive,
            'set_count' => $this->setCount,
        ];
    }
}
