<?php

declare(strict_types=1);

namespace Sstf\Api\Services;

use Sstf\Api\Domain\CatalogExerciseNotFoundException;
use Sstf\Api\Domain\InvalidSetException;
use Sstf\Api\Domain\TrainingSet;
use Sstf\Api\Infrastructure\Sqlite\ExerciseRepository;
use Sstf\Api\Infrastructure\Sqlite\SetRepository;

final class SetService
{
    public const MIN_DAY = 0;
    public const MAX_DAY = 6;
    public const MIN_MINUTES = 0;
    public const MAX_MINUTES = 1439;

    public function __construct(
        private readonly SetRepository $sets,
        private readonly ExerciseRepository $exercises,
    ) {
    }

    /**
     * @return list<TrainingSet>
     */
    public function listForSchedule(string $emailHash, int $scheduleId): array
    {
        return $this->sets->listForSchedule($emailHash, $scheduleId);
    }

    public function create(
        string $emailHash,
        int $scheduleId,
        string $name,
        int $dayOfWeek,
        int $startMinutes,
        int $sortOrder,
    ): TrainingSet {
        $this->assertDay($dayOfWeek);
        $this->assertMinutes($startMinutes);

        return $this->sets->create(
            $emailHash,
            $scheduleId,
            $this->requireName($name),
            $dayOfWeek,
            $startMinutes,
            $sortOrder,
        );
    }

    /**
     * @param array{name?: string, day_of_week?: int, start_minutes?: int, sort_order?: int} $fields
     */
    public function patch(string $emailHash, int $id, array $fields): TrainingSet
    {
        $current = $this->sets->getLive($emailHash, $id);
        $name = $current->name;
        $day = $current->dayOfWeek;
        $minutes = $current->startMinutes;
        $order = $current->sortOrder;

        if (array_key_exists('name', $fields)) {
            $name = $this->requireName($fields['name']);
        }
        if (array_key_exists('day_of_week', $fields)) {
            $day = $fields['day_of_week'];
            $this->assertDay($day);
        }
        if (array_key_exists('start_minutes', $fields)) {
            $minutes = $fields['start_minutes'];
            $this->assertMinutes($minutes);
        }
        if (array_key_exists('sort_order', $fields)) {
            $order = $fields['sort_order'];
        }

        return $this->sets->update($emailHash, $id, $name, $day, $minutes, $order);
    }

    public function delete(string $emailHash, int $id): void
    {
        $this->sets->delete($emailHash, $id);
    }

    /**
     * @param list<int> $globalExerciseIds
     */
    public function replaceExercises(string $emailHash, int $setId, array $globalExerciseIds): TrainingSet
    {
        $rows = [];
        $sort = 0;
        foreach ($globalExerciseIds as $globalId) {
            $exercise = $this->exercises->findById($globalId);
            if ($exercise === null) {
                throw new CatalogExerciseNotFoundException();
            }
            $rows[] = [
                'global_exercise_id' => $exercise->id,
                'name' => $exercise->name,
                'muscle_group' => $exercise->muscleGroup,
                'equipment' => $exercise->equipment,
                'sort_order' => $sort,
            ];
            $sort++;
        }

        return $this->sets->replaceExercises($emailHash, $setId, $rows);
    }

    private function requireName(string $name): string
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new InvalidSetException();
        }

        return $trimmed;
    }

    private function assertDay(int $dayOfWeek): void
    {
        if ($dayOfWeek < self::MIN_DAY || $dayOfWeek > self::MAX_DAY) {
            throw new InvalidSetException();
        }
    }

    private function assertMinutes(int $startMinutes): void
    {
        if ($startMinutes < self::MIN_MINUTES || $startMinutes > self::MAX_MINUTES) {
            throw new InvalidSetException();
        }
    }
}
