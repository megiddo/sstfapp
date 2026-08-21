<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final readonly class HistoryDay
{
    /**
     * @param list<ExerciseLog> $logs
     */
    public function __construct(
        public string $date,
        public array $logs,
    ) {
    }

    /**
     * @return array{date: string, logs: list<array<string, mixed>>}
     */
    public function toApi(): array
    {
        $logs = [];
        foreach ($this->logs as $log) {
            $logs[] = $log->toApi();
        }

        return [
            'date' => $this->date,
            'logs' => $logs,
        ];
    }
}
