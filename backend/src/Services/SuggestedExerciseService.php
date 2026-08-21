<?php

declare(strict_types=1);

namespace Sstf\Api\Services;

use Sstf\Api\Domain\Exercise;
use Sstf\Api\Infrastructure\Sqlite\LogRepository;

final class SuggestedExerciseService
{
    public function __construct(
        private readonly LogRepository $logs,
    ) {
    }

    /**
     * @return array{recent: list<Exercise>, frequent: list<Exercise>}
     */
    public function forUser(string $emailHash): array
    {
        return $this->logs->suggested($emailHash);
    }
}
