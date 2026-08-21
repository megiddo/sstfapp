<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\HistoryFilters;
use Sstf\Api\Domain\InvalidHistoryFilterException;

#[CoversClass(HistoryFilters::class)]
#[CoversClass(InvalidHistoryFilterException::class)]
final class HistoryFiltersTest extends TestCase
{
    public function testEmptyQueryIsOpenRange(): void
    {
        $filters = HistoryFilters::fromQuery([]);
        $this->assertNull($filters->from);
        $this->assertNull($filters->to);
        $this->assertNull($filters->exerciseId);
        $this->assertTrue($filters->matchesDay('2026-08-19'));
        $this->assertTrue($filters->matchesDay('1970-01-01'));
    }

    public function testBlankStringsAreIgnored(): void
    {
        $filters = HistoryFilters::fromQuery([
            'from' => '',
            'to' => '',
            'exercise_id' => '',
        ]);
        $this->assertNull($filters->from);
        $this->assertNull($filters->to);
        $this->assertNull($filters->exerciseId);
    }

    public function testParsesDaysAndExerciseId(): void
    {
        $filters = HistoryFilters::fromQuery([
            'from' => '2026-08-19',
            'to' => '2026-08-20',
            'exercise_id' => '12',
        ]);
        $this->assertSame('2026-08-19', $filters->from);
        $this->assertSame('2026-08-20', $filters->to);
        $this->assertSame(12, $filters->exerciseId);
        $this->assertTrue($filters->matchesDay('2026-08-19'));
        $this->assertTrue($filters->matchesDay('2026-08-20'));
        $this->assertFalse($filters->matchesDay('2026-08-18'));
        $this->assertFalse($filters->matchesDay('2026-08-21'));
    }

    public function testIntegerExerciseIdIsAccepted(): void
    {
        $filters = HistoryFilters::fromQuery(['exercise_id' => 7]);
        $this->assertSame(7, $filters->exerciseId);
    }

    public function testFromOnlyAndToOnly(): void
    {
        $from = HistoryFilters::fromQuery(['from' => '2026-08-19']);
        $this->assertFalse($from->matchesDay('2026-08-18'));
        $this->assertTrue($from->matchesDay('2026-08-19'));
        $this->assertTrue($from->matchesDay('2030-01-01'));

        $to = HistoryFilters::fromQuery(['to' => '2026-08-19']);
        $this->assertTrue($to->matchesDay('2020-01-01'));
        $this->assertTrue($to->matchesDay('2026-08-19'));
        $this->assertFalse($to->matchesDay('2026-08-20'));
    }

    public function testRejectsInvalidDatesAndIds(): void
    {
        $queries = [
            ['from' => '08-19-2026'],
            ['from' => '2026-13-01'],
            ['from' => '2026-02-31'],
            ['from' => ['2026-08-19']],
            ['to' => 'nope'],
            ['exercise_id' => '0'],
            ['exercise_id' => '-1'],
            ['exercise_id' => 0],
            ['exercise_id' => '1.5'],
            ['exercise_id' => 'abc'],
            ['exercise_id' => ['1']],
        ];
        $caught = 0;
        foreach ($queries as $query) {
            try {
                HistoryFilters::fromQuery($query);
                $this->fail('Expected invalid filter for ' . json_encode($query));
            } catch (InvalidHistoryFilterException) {
                $caught++;
            }
        }
        $this->assertSame(count($queries), $caught);
    }
}
