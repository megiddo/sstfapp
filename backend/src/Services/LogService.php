<?php

declare(strict_types=1);

namespace Sstf\Api\Services;

use Sstf\Api\Domain\ExerciseLog;
use Sstf\Api\Domain\ExerciseNotOnSetException;
use Sstf\Api\Domain\HistoryDay;
use Sstf\Api\Domain\HistoryGrouper;
use Sstf\Api\Domain\InvalidLogException;
use Sstf\Api\Domain\SetExercise;
use Sstf\Api\Domain\SetNotFoundException;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Infrastructure\Sqlite\LogRepository;
use Sstf\Api\Infrastructure\Sqlite\ScheduleRepository;
use Sstf\Api\Infrastructure\Sqlite\SetRepository;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;

final class LogService
{
    public function __construct(
        private readonly UserDirectory $accounts,
        private readonly ScheduleRepository $schedules,
        private readonly SetRepository $sets,
        private readonly LogRepository $logs,
    ) {
    }

    public function create(
        string $emailHash,
        int $setId,
        int $globalExerciseId,
        float $weight,
        int $reps,
        ?string $notes,
    ): ExerciseLog {
        if ($weight < 0 || $reps < 0) {
            throw new InvalidLogException();
        }

        $account = $this->accounts->loadAccount($emailHash);
        if ($account === null) {
            throw new UnauthenticatedException();
        }

        $schedule = $this->schedules->findActive($emailHash);
        if ($schedule === null) {
            throw new SetNotFoundException();
        }

        $set = $this->sets->getLive($emailHash, $setId);
        if ($set->scheduleId !== $schedule->id) {
            throw new SetNotFoundException();
        }

        $exercise = $this->exerciseOnSet($set->exercises, $globalExerciseId);
        if ($exercise === null) {
            throw new ExerciseNotOnSetException();
        }

        $trimmedNotes = $notes === null ? null : trim($notes);
        if ($trimmedNotes === '') {
            $trimmedNotes = null;
        }

        return $this->logs->insert(
            $emailHash,
            $schedule->id,
            $schedule->name,
            $set->id,
            $set->name,
            $set->dayOfWeek,
            $set->startMinutes,
            $exercise->globalExerciseId,
            $exercise->name,
            $exercise->muscleGroup,
            $weight,
            $account->weightUnit,
            $reps,
            $trimmedNotes,
        );
    }

    /**
     * @return list<HistoryDay>
     */
    public function history(string $emailHash): array
    {
        $account = $this->accounts->loadAccount($emailHash);
        if ($account === null) {
            throw new UnauthenticatedException();
        }

        return HistoryGrouper::groupByDay(
            $this->logs->listAll($emailHash),
            $account->timezone,
        );
    }

    /**
     * @param list<SetExercise> $exercises
     */
    private function exerciseOnSet(array $exercises, int $globalExerciseId): ?SetExercise
    {
        foreach ($exercises as $exercise) {
            if ($exercise->globalExerciseId === $globalExerciseId) {
                return $exercise;
            }
        }

        return null;
    }
}
