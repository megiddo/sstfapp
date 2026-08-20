<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\ClosestSet;
use Sstf\Api\Domain\TrainingSet;

#[CoversClass(ClosestSet::class)]
#[CoversClass(TrainingSet::class)]
final class ClosestSetTest extends TestCase
{
    public function testEmptyListReturnsNull(): void
    {
        $now = new DateTimeImmutable('2026-08-19 18:40:00', new DateTimeZone('America/Chicago'));
        $this->assertNull(ClosestSet::pick([], $now));
    }

    public function testHourAgoBeatsTomorrowMorning(): void
    {
        $now = new DateTimeImmutable('2026-08-19 18:40:00', new DateTimeZone('America/Chicago'));
        $evening = $this->set(1, 'Evening', 3, 1080);
        $morning = $this->set(2, 'Morning', 4, 420);

        $picked = ClosestSet::pick([$morning, $evening], $now);
        $this->assertNotNull($picked);
        $this->assertSame(1, $picked->id);
        $this->assertSame('Evening', $picked->name);
    }

    public function testFutureHourBeatsYesterdaySameClock(): void
    {
        $now = new DateTimeImmutable('2026-08-19 17:00:00', new DateTimeZone('America/Chicago'));
        $evening = $this->set(1, 'Evening', 3, 1080);
        $tuesday = $this->set(2, 'Tuesday', 2, 1080);

        $picked = ClosestSet::pick([$tuesday, $evening], $now);
        $this->assertNotNull($picked);
        $this->assertSame(1, $picked->id);
    }

    public function testWeekWrapSaturdayBeatsMondayOnSundayMorning(): void
    {
        $now = new DateTimeImmutable('2026-08-23 00:30:00', new DateTimeZone('America/Chicago'));
        $this->assertSame('0', $now->format('w'));
        $saturday = $this->set(4, 'Late', 6, 1380);
        $monday = $this->set(5, 'Monday', 1, 420);

        $picked = ClosestSet::pick([$monday, $saturday], $now);
        $this->assertNotNull($picked);
        $this->assertSame(4, $picked->id);
        $this->assertSame('Late', $picked->name);
    }

    public function testTiePrefersSameCalendarDay(): void
    {
        $now = new DateTimeImmutable('2026-08-19 00:00:00', new DateTimeZone('America/Chicago'));
        $tuesday = $this->set(1, 'Tuesday late', 2, 1320);
        $wednesday = $this->set(2, 'Wednesday early', 3, 120);

        $picked = ClosestSet::pick([$tuesday, $wednesday], $now);
        $this->assertNotNull($picked);
        $this->assertSame(2, $picked->id);
        $this->assertSame('Wednesday early', $picked->name);
    }

    public function testTieOnSameDayPrefersEarlierStartMinutes(): void
    {
        $now = new DateTimeImmutable('2026-08-19 12:00:00', new DateTimeZone('America/Chicago'));
        $later = $this->set(8, 'Afternoon', 3, 840);
        $earlier = $this->set(9, 'Morning', 3, 600);

        $picked = ClosestSet::pick([$later, $earlier], $now);
        $this->assertNotNull($picked);
        $this->assertSame(9, $picked->id);
        $this->assertSame(600, $picked->startMinutes);
    }

    public function testTieOnSameTimePrefersLowerId(): void
    {
        $now = new DateTimeImmutable('2026-08-19 18:00:00', new DateTimeZone('America/Chicago'));
        $high = $this->set(20, 'B', 3, 1080);
        $low = $this->set(10, 'A', 3, 1080);

        $picked = ClosestSet::pick([$high, $low], $now);
        $this->assertNotNull($picked);
        $this->assertSame(10, $picked->id);
    }

    public function testTimezoneChangesWhichWeekdayOccurrenceWins(): void
    {
        $chicago = new DateTimeImmutable('2026-08-22 22:00:00', new DateTimeZone('America/Chicago'));
        $utc = $chicago->setTimezone(new DateTimeZone('UTC'));
        $this->assertSame('6', $chicago->format('w'));
        $this->assertSame('0', $utc->format('w'));

        $saturday = $this->set(1, 'Saturday', 6, 1260);
        $sunday = $this->set(2, 'Sunday', 0, 240);

        $inChicago = ClosestSet::pick([$saturday, $sunday], $chicago);
        $inUtc = ClosestSet::pick([$saturday, $sunday], $utc);
        $this->assertNotNull($inChicago);
        $this->assertNotNull($inUtc);
        $this->assertSame(1, $inChicago->id);
        $this->assertSame(2, $inUtc->id);
    }

    public function testEqualDeltaNeitherTodayPrefersEarlierStartMinutes(): void
    {
        $now = new DateTimeImmutable('2026-08-20 12:00:00', new DateTimeZone('America/Chicago'));
        $this->assertSame('4', $now->format('w'));
        $wednesdayEarly = $this->set(1, 'Wed AM', 3, 360);
        $fridayLate = $this->set(2, 'Fri PM', 5, 1080);

        $picked = ClosestSet::pick([$fridayLate, $wednesdayEarly], $now);
        $this->assertNotNull($picked);
        $this->assertSame(1, $picked->id);
    }

    public function testSingleSetIsPickedEvenFarAway(): void
    {
        $now = new DateTimeImmutable('2026-08-19 12:00:00', new DateTimeZone('UTC'));
        $only = $this->set(3, 'Only', 0, 0);
        $picked = ClosestSet::pick([$only], $now);
        $this->assertNotNull($picked);
        $this->assertSame(3, $picked->id);
    }

    public function testDoesNotPreferUpcomingWhenPastIsCloser(): void
    {
        $now = new DateTimeImmutable('2026-08-19 18:40:00', new DateTimeZone('America/Chicago'));
        $past = $this->set(1, 'Evening', 3, 1080);
        $upcoming = $this->set(2, 'Morning', 4, 420);
        $picked = ClosestSet::pick([$upcoming, $past], $now);
        $this->assertNotNull($picked);
        $this->assertSame('Evening', $picked->name);
        $this->assertNotSame('Morning', $picked->name);
    }

    public function testEqualDeltaSameDayKeepsEarlierStartOverLater(): void
    {
        $now = new DateTimeImmutable('2026-08-19 12:00:00', new DateTimeZone('America/Chicago'));
        $morning = $this->set(1, 'AM', 3, 600);
        $afternoon = $this->set(2, 'PM', 3, 840);
        $this->assertSame(ClosestSet::pick([$morning, $afternoon], $now)?->id, 1);
        $this->assertSame(ClosestSet::pick([$afternoon, $morning], $now)?->id, 1);
    }

    public function testNextWeekCandidateIsConsideredWhenCurrentWeekIsFar(): void
    {
        $now = new DateTimeImmutable('2026-08-22 23:30:00', new DateTimeZone('America/Chicago'));
        $this->assertSame('6', $now->format('w'));
        $sundayEarly = $this->set(1, 'Sunday AM', 0, 30);
        $picked = ClosestSet::pick([$sundayEarly], $now);
        $this->assertNotNull($picked);
        $this->assertSame(1, $picked->id);
    }

    private function set(int $id, string $name, int $day, int $minutes): TrainingSet
    {
        return new TrainingSet($id, 1, $name, $day, $minutes, 0, []);
    }
}
