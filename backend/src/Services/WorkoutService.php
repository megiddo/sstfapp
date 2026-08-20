<?php

declare(strict_types=1);

namespace Sstf\Api\Services;

use DateTimeImmutable;
use DateTimeZone;
use Sstf\Api\Domain\ClockInterface;
use Sstf\Api\Domain\ClosestSet;
use Sstf\Api\Domain\SetNotFoundException;
use Sstf\Api\Domain\TrainingSet;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Domain\WorkoutCurrent;
use Sstf\Api\Domain\WorkoutSetSummary;
use Sstf\Api\Infrastructure\Sqlite\LogRepository;
use Sstf\Api\Infrastructure\Sqlite\ScheduleRepository;
use Sstf\Api\Infrastructure\Sqlite\SetRepository;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;

final class WorkoutService
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly UserDirectory $accounts,
        private readonly ScheduleRepository $schedules,
        private readonly SetRepository $sets,
        private readonly LogRepository $logs,
    ) {
    }

    public function current(string $emailHash, ?int $overrideSetId): WorkoutCurrent
    {
        $account = $this->accounts->loadAccount($emailHash);
        if ($account === null) {
            throw new UnauthenticatedException();
        }

        $schedule = $this->schedules->findActive($emailHash);
        if ($schedule === null) {
            return new WorkoutCurrent(null, null, $account->weightUnit, 'no_schedule', [], null);
        }

        $sets = $this->sets->listForSchedule($emailHash, $schedule->id);
        $now = $this->nowIn($account->timezone);
        $closest = ClosestSet::pick($sets, $now);
        $closestId = $closest?->id;

        if ($overrideSetId !== null) {
            $chosen = $this->setOnActiveSchedule($sets, $overrideSetId);
            if ($chosen === null) {
                throw new SetNotFoundException();
            }
        } else {
            $chosen = $closest;
        }

        if ($chosen === null) {
            return new WorkoutCurrent($schedule, null, $account->weightUnit, 'no_sets', [], $closestId);
        }

        $empty = $chosen->exercises === [] ? 'no_exercises' : null;

        return new WorkoutCurrent(
            $schedule,
            $chosen,
            $account->weightUnit,
            $empty,
            $this->prefillExercises($emailHash, $chosen),
            $closestId,
        );
    }

    /**
     * @return array{schedule: ?array{id: int, name: string, is_active: bool, set_count: int}, closest_set_id: ?int, sets: list<array{
     *   id: int,
     *   name: string,
     *   day_of_week: int,
     *   start_minutes: int,
     *   exercise_count: int,
     *   is_closest: bool
     * }>}
     */
    public function sets(string $emailHash): array
    {
        $account = $this->accounts->loadAccount($emailHash);
        if ($account === null) {
            throw new UnauthenticatedException();
        }

        $schedule = $this->schedules->findActive($emailHash);
        if ($schedule === null) {
            return [
                'schedule' => null,
                'closest_set_id' => null,
                'sets' => [],
            ];
        }

        $sets = $this->sets->listForSchedule($emailHash, $schedule->id);
        $closest = ClosestSet::pick($sets, $this->nowIn($account->timezone));
        $closestId = $closest?->id;
        $summaries = [];
        foreach ($sets as $set) {
            $summary = new WorkoutSetSummary(
                $set->id,
                $set->name,
                $set->dayOfWeek,
                $set->startMinutes,
                count($set->exercises),
                $closestId !== null && $set->id === $closestId,
            );
            $summaries[] = $summary->toApi();
        }

        return [
            'schedule' => $schedule->toApi(),
            'closest_set_id' => $closestId,
            'sets' => $summaries,
        ];
    }

    /**
     * @param list<TrainingSet> $sets
     */
    private function setOnActiveSchedule(array $sets, int $setId): ?TrainingSet
    {
        foreach ($sets as $set) {
            if ($set->id === $setId) {
                return $set;
            }
        }

        return null;
    }

    /**
     * @return list<array{
     *   id: int,
     *   global_exercise_id: ?int,
     *   name: string,
     *   muscle_group: ?string,
     *   equipment: ?string,
     *   last_weight: ?float,
     *   last_reps: ?int
     * }>
     */
    private function prefillExercises(string $emailHash, TrainingSet $set): array
    {
        $out = [];
        foreach ($set->exercises as $exercise) {
            $prefill = null;
            if ($exercise->globalExerciseId !== null) {
                $prefill = $this->logs->latestForSetExercise($emailHash, $set->id, $exercise->globalExerciseId);
                if ($prefill === null) {
                    $prefill = $this->logs->latestForExercise($emailHash, $exercise->globalExerciseId);
                }
            }
            $out[] = [
                'id' => $exercise->id,
                'global_exercise_id' => $exercise->globalExerciseId,
                'name' => $exercise->name,
                'muscle_group' => $exercise->muscleGroup,
                'equipment' => $exercise->equipment,
                'last_weight' => $prefill?->weight,
                'last_reps' => $prefill?->reps,
            ];
        }

        return $out;
    }

    private function nowIn(string $timezone): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone($timezone));
    }
}
