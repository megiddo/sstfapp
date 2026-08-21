<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\ExerciseLog;
use Sstf\Api\Domain\HistoryDay;
use Sstf\Api\Domain\HistoryFilters;
use Sstf\Api\Domain\HistoryGrouper;
use Sstf\Api\Domain\IanaTimezone;

#[CoversClass(HistoryGrouper::class)]
#[CoversClass(HistoryDay::class)]
#[CoversClass(HistoryFilters::class)]
#[CoversClass(ExerciseLog::class)]
#[CoversClass(IanaTimezone::class)]
final class HistoryGrouperTest extends TestCase
{
    public function testEmptyLogsYieldNoDays(): void
    {
        $this->assertSame([], HistoryGrouper::groupByDay([], 'America/Chicago'));
    }

    public function testGroupsTwoDaysInAccountTimezoneAndKeepsNewestFirst(): void
    {
        $lateChicago = $this->log(2, '2026-08-20T04:30:00+00:00', 'Evening', 'Bench Press', 185, 'lb', 8);
        $earlyNext = $this->log(1, '2026-08-20T05:30:00+00:00', 'Morning', 'Squat', 225, 'kg', 5);

        $days = HistoryGrouper::groupByDay([$earlyNext, $lateChicago], 'America/Chicago');

        $this->assertCount(2, $days);
        $this->assertSame('2026-08-20', $days[0]->date);
        $this->assertSame('2026-08-19', $days[1]->date);
        $this->assertSame('Squat', $days[0]->logs[0]->exerciseName);
        $this->assertSame('kg', $days[0]->logs[0]->weightUnit);
        $this->assertSame('Bench Press', $days[1]->logs[0]->exerciseName);
        $this->assertSame('lb', $days[1]->logs[0]->weightUnit);

        $utcDays = HistoryGrouper::groupByDay([$earlyNext, $lateChicago], 'UTC');
        $this->assertCount(1, $utcDays);
        $this->assertSame('2026-08-20', $utcDays[0]->date);
        $this->assertCount(2, $utcDays[0]->logs);
    }

    public function testInvalidTimezoneFallsBackToUtcAndInvalidStampUsesEpoch(): void
    {
        $valid = $this->log(3, '2026-08-20T04:30:00+00:00', 'Evening', 'Row', 135, 'lb', 10);
        $bogus = $this->log(4, 'not-a-timestamp', 'Evening', 'Curl', 40, 'lb', 12);

        $days = HistoryGrouper::groupByDay([$valid, $bogus], 'Not/A_Zone');
        $this->assertCount(2, $days);
        $this->assertSame('2026-08-20', $days[0]->date);
        $this->assertSame('1970-01-01', $days[1]->date);

        $payload = $days[0]->toApi();
        $this->assertSame('2026-08-20', $payload['date']);
        $this->assertSame('Row', $payload['logs'][0]['exercise_name']);
        $this->assertSame('lb', $payload['logs'][0]['weight_unit']);
    }

    public function testSameLocalDayKeepsLogOrder(): void
    {
        $second = $this->log(8, '2026-08-19T18:00:00+00:00', 'Evening', 'Row', 135, 'lb', 10);
        $first = $this->log(7, '2026-08-19T17:00:00+00:00', 'Evening', 'Bench Press', 185, 'lb', 8);

        $days = HistoryGrouper::groupByDay([$second, $first], 'UTC');
        $this->assertCount(1, $days);
        $this->assertSame('2026-08-19', $days[0]->date);
        $this->assertSame([8, 7], array_map(static fn (ExerciseLog $log): int => $log->id, $days[0]->logs));
    }

    public function testInDateRangeKeepsInclusiveBounds(): void
    {
        $a = new HistoryDay('2026-08-18', [$this->log(1, '2026-08-18T18:00:00+00:00', 'Evening', 'Row', 135, 'lb', 10)]);
        $b = new HistoryDay('2026-08-19', [$this->log(2, '2026-08-19T18:00:00+00:00', 'Evening', 'Bench Press', 185, 'lb', 8)]);
        $c = new HistoryDay('2026-08-20', [$this->log(3, '2026-08-20T18:00:00+00:00', 'Evening', 'Squat', 225, 'lb', 5)]);
        $days = [$a, $b, $c];

        $filtered = HistoryGrouper::inDateRange($days, HistoryFilters::fromQuery([
            'from' => '2026-08-19',
            'to' => '2026-08-19',
        ]));
        $this->assertCount(1, $filtered);
        $this->assertSame('2026-08-19', $filtered[0]->date);

        $open = HistoryGrouper::inDateRange($days, HistoryFilters::fromQuery([]));
        $this->assertCount(3, $open);
        $this->assertSame($days, $open);
    }

    private function log(
        int $id,
        string $loggedAt,
        string $setName,
        string $exerciseName,
        float $weight,
        string $unit,
        int $reps,
    ): ExerciseLog {
        return new ExerciseLog(
            $id,
            $loggedAt,
            1,
            'Hypertrophy',
            9,
            $setName,
            3,
            1080,
            1,
            $exerciseName,
            'Chest',
            $weight,
            $unit,
            $reps,
            null,
        );
    }
}
