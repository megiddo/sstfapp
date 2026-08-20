<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\DuplicateExerciseNameException;
use Sstf\Api\Domain\Exercise;
use Sstf\Api\Domain\InvalidExerciseException;
use Sstf\Api\Infrastructure\Sqlite\ExerciseRepository;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Services\ExerciseService;
use Sstf\Api\Tests\Fakes\FakeClock;

#[CoversClass(ExerciseService::class)]
#[CoversClass(ExerciseRepository::class)]
#[CoversClass(Exercise::class)]
#[CoversClass(DuplicateExerciseNameException::class)]
#[CoversClass(InvalidExerciseException::class)]
final class ExerciseServiceTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-ex-svc-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testListSearchCreateAndOptionalFields(): void
    {
        $clock = new FakeClock(1_700_000_000);
        $service = $this->service($clock);

        $all = $service->list(null);
        $this->assertGreaterThan(15, count($all));
        $this->assertSame($all[0]->name, $service->list('')[0]->name);
        $this->assertSame($all[0]->name, $service->list('   ')[0]->name);

        $bench = $service->list('  bench  ');
        $names = [];
        foreach ($bench as $exercise) {
            $names[] = $exercise->name;
        }
        $this->assertContains('Bench Press', $names);
        $this->assertNotContains('Squat', $names);

        $created = $service->create('  Farmer Carry  ', '  Core  ', '  ', null);
        $this->assertSame('Farmer Carry', $created->name);
        $this->assertSame('Core', $created->muscleGroup);
        $this->assertNull($created->equipment);
        $this->assertNull($created->notes);
        $this->assertSame(
            [
                'id' => $created->id,
                'name' => 'Farmer Carry',
                'muscle_group' => 'Core',
                'equipment' => null,
                'notes' => null,
            ],
            $created->toApi(),
        );

        $found = $service->list('farmer');
        $this->assertCount(1, $found);
        $this->assertSame('Farmer Carry', $found[0]->name);

        $percent = $service->create('100% Press', null, null, null);
        $this->assertSame('100% Press', $percent->name);
        $this->assertCount(1, $service->list('100%'));
        $this->assertSame('100% Press', $service->list('%')[0]->name);
        $this->assertCount(1, $service->list('%'));
        $this->assertLessThan(count($all), count($service->list('%')));

        $this->expectException(InvalidExerciseException::class);
        $service->create('   ', null, null, null);
    }

    public function testDuplicateNameThrows(): void
    {
        $service = $this->service(new FakeClock(1_700_000_000));
        $service->create('Unique Move', null, null, 'note');
        $this->expectException(DuplicateExerciseNameException::class);
        $service->create('unique move', 'Arms', 'Cable', 'other');
    }

    private function service(FakeClock $clock): ExerciseService
    {
        $root = dirname(__DIR__, 3);
        $global = new GlobalDb(
            $this->tmp . '/global.sqlite',
            new Migrator(),
            $root . '/migrations/global',
        );

        return new ExerciseService(new ExerciseRepository($global, $clock));
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            if (is_dir($full)) {
                $this->deleteTree($full);
            } else {
                unlink($full);
            }
        }
        rmdir($path);
    }
}
