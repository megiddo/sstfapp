<?php

declare(strict_types=1);

namespace Sstf\Api\Services;

use Sstf\Api\Domain\InvalidScheduleException;
use Sstf\Api\Domain\Schedule;
use Sstf\Api\Infrastructure\Sqlite\ScheduleRepository;

final class ScheduleService
{
    public function __construct(
        private readonly ScheduleRepository $schedules,
    ) {
    }

    /**
     * @return list<Schedule>
     */
    public function list(string $emailHash): array
    {
        return $this->schedules->listLive($emailHash);
    }

    public function create(string $emailHash, string $name): Schedule
    {
        return $this->schedules->create($emailHash, $this->requireName($name));
    }

    public function rename(string $emailHash, int $id, string $name): Schedule
    {
        return $this->schedules->rename($emailHash, $id, $this->requireName($name));
    }

    public function activate(string $emailHash, int $id): Schedule
    {
        return $this->schedules->activate($emailHash, $id);
    }

    public function archive(string $emailHash, int $id): void
    {
        $this->schedules->archive($emailHash, $id);
    }

    private function requireName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidScheduleException();
        }

        return $trimmed;
    }
}
