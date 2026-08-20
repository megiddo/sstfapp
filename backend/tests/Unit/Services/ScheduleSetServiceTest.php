<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\CatalogExerciseNotFoundException;
use Sstf\Api\Domain\InvalidScheduleException;
use Sstf\Api\Domain\InvalidSetException;
use Sstf\Api\Domain\Schedule;
use Sstf\Api\Domain\ScheduleNotFoundException;
use Sstf\Api\Domain\SetExercise;
use Sstf\Api\Domain\SetNotFoundException;
use Sstf\Api\Domain\TrainingSet;
use Sstf\Api\Infrastructure\Sqlite\ExerciseRepository;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\ScheduleRepository;
use Sstf\Api\Infrastructure\Sqlite\SetRepository;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Services\ScheduleService;
use Sstf\Api\Services\SetService;
use Sstf\Api\Tests\Fakes\FakeClock;

#[CoversClass(ScheduleService::class)]
#[CoversClass(SetService::class)]
#[CoversClass(ScheduleRepository::class)]
#[CoversClass(SetRepository::class)]
#[CoversClass(ExerciseRepository::class)]
#[CoversClass(Schedule::class)]
#[CoversClass(TrainingSet::class)]
#[CoversClass(SetExercise::class)]
#[CoversClass(InvalidScheduleException::class)]
#[CoversClass(InvalidSetException::class)]
#[CoversClass(ScheduleNotFoundException::class)]
#[CoversClass(SetNotFoundException::class)]
#[CoversClass(CatalogExerciseNotFoundException::class)]
final class ScheduleSetServiceTest extends TestCase
{
    private string $tmp;

    private string $hash;

    private ScheduleService $schedules;

    private SetService $sets;

    private ExerciseRepository $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-sched-svc-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/users', 0700, true);
        $this->hash = str_repeat('ab', 16);
        $root = dirname(__DIR__, 3);
        $clock = new FakeClock(1_700_000_000);
        $users = new UserDbFactory($this->tmp . '/users', new Migrator(), $root . '/migrations/user');
        $users->open($this->hash);
        $global = new GlobalDb($this->tmp . '/global.sqlite', new Migrator(), $root . '/migrations/global');
        $this->catalog = new ExerciseRepository($global, $clock);
        $this->schedules = new ScheduleService(new ScheduleRepository($users, $clock));
        $this->sets = new SetService(new SetRepository($users, $clock), $this->catalog);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testCreateListRenameActivateArchiveAndSetLifecycle(): void
    {
        $this->assertSame([], $this->schedules->list($this->hash));

        $first = $this->schedules->create($this->hash, '  A  ');
        $this->assertSame('A', $first->name);
        $this->assertTrue($first->isActive);
        $this->assertSame(
            ['id' => $first->id, 'name' => 'A', 'is_active' => true, 'set_count' => 0],
            $first->toApi(),
        );

        $second = $this->schedules->create($this->hash, 'B');
        $this->assertFalse($second->isActive);

        $renamed = $this->schedules->rename($this->hash, $first->id, 'Alpha');
        $this->assertSame('Alpha', $renamed->name);

        $activated = $this->schedules->activate($this->hash, $second->id);
        $this->assertTrue($activated->isActive);
        $listed = $this->schedules->list($this->hash);
        $this->assertTrue($listed[1]->isActive);
        $this->assertFalse($listed[0]->isActive);

        $set = $this->sets->create($this->hash, $second->id, '  Evening  ', 3, 1080, 0);
        $this->assertSame('Evening', $set->name);
        $this->assertSame(3, $set->dayOfWeek);
        $this->assertSame(1080, $set->startMinutes);
        $this->assertSame([], $set->exercises);

        $bench = $this->catalog->search('Bench Press')[0];
        $row = $this->catalog->search('Barbell Row')[0];
        $withEx = $this->sets->replaceExercises($this->hash, $set->id, [$bench->id, $row->id]);
        $this->assertCount(2, $withEx->exercises);
        $this->assertSame('Bench Press', $withEx->exercises[0]->name);
        $this->assertSame($bench->id, $withEx->exercises[0]->globalExerciseId);
        $api = $withEx->toApi();
        $this->assertSame('Barbell Row', $api['exercises'][1]['name']);

        $patched = $this->sets->patch($this->hash, $set->id, [
            'name' => 'Night',
            'day_of_week' => 4,
            'start_minutes' => 1170,
            'sort_order' => 5,
        ]);
        $this->assertSame('Night', $patched->name);
        $this->assertSame(4, $patched->dayOfWeek);
        $this->assertSame(1170, $patched->startMinutes);
        $this->assertSame(5, $patched->sortOrder);
        $this->assertCount(2, $patched->exercises);

        $partial = $this->sets->patch($this->hash, $set->id, []);
        $this->assertSame('Night', $partial->name);

        $this->assertCount(1, $this->sets->listForSchedule($this->hash, $second->id));
        $this->sets->delete($this->hash, $set->id);
        $this->assertSame([], $this->sets->listForSchedule($this->hash, $second->id));

        $this->schedules->archive($this->hash, $second->id);
        $remaining = $this->schedules->list($this->hash);
        $this->assertCount(1, $remaining);
        $this->assertSame('Alpha', $remaining[0]->name);
        $this->assertFalse($remaining[0]->isActive);

        $this->expectException(ScheduleNotFoundException::class);
        $this->schedules->activate($this->hash, $second->id);
    }

    public function testBlankScheduleNameIsInvalid(): void
    {
        $this->expectException(InvalidScheduleException::class);
        $this->schedules->create($this->hash, '   ');
    }

    public function testBlankRenameIsInvalid(): void
    {
        $created = $this->schedules->create($this->hash, 'Keep');
        $this->expectException(InvalidScheduleException::class);
        $this->schedules->rename($this->hash, $created->id, '');
    }

    public function testMissingScheduleIsNotFound(): void
    {
        $this->expectException(ScheduleNotFoundException::class);
        $this->schedules->rename($this->hash, 99, 'X');
    }

    public function testInvalidDayAndMinutesThrow(): void
    {
        $schedule = $this->schedules->create($this->hash, 'P');
        try {
            $this->sets->create($this->hash, $schedule->id, 'X', -1, 0, 0);
            $this->fail('expected invalid day');
        } catch (InvalidSetException) {
        }
        try {
            $this->sets->create($this->hash, $schedule->id, 'X', 7, 0, 0);
            $this->fail('expected invalid day');
        } catch (InvalidSetException) {
        }
        try {
            $this->sets->create($this->hash, $schedule->id, 'X', 0, -1, 0);
            $this->fail('expected invalid minutes');
        } catch (InvalidSetException) {
        }
        try {
            $this->sets->create($this->hash, $schedule->id, 'X', 0, 1440, 0);
            $this->fail('expected invalid minutes');
        } catch (InvalidSetException) {
        }

        $ok = $this->sets->create($this->hash, $schedule->id, 'X', 0, 0, 0);
        $this->expectException(InvalidSetException::class);
        $this->sets->patch($this->hash, $ok->id, ['name' => '  ']);
    }

    public function testCatalogMissAndMissingSet(): void
    {
        $schedule = $this->schedules->create($this->hash, 'P');
        $set = $this->sets->create($this->hash, $schedule->id, 'S', 1, 60, 0);
        $this->assertNull($this->catalog->findById(0));
        $this->assertNull($this->catalog->findById(999999));
        $found = $this->catalog->findById($this->catalog->search('Plank')[0]->id);
        $this->assertNotNull($found);
        $this->assertSame('Plank', $found->name);

        try {
            $this->sets->replaceExercises($this->hash, $set->id, [999999]);
            $this->fail('expected catalog miss');
        } catch (CatalogExerciseNotFoundException) {
        }

        $this->sets->delete($this->hash, $set->id);
        $this->expectException(SetNotFoundException::class);
        $this->sets->patch($this->hash, $set->id, ['name' => 'Gone']);
    }

    public function testCreateSetOnArchivedScheduleFails(): void
    {
        $schedule = $this->schedules->create($this->hash, 'Gone');
        $this->schedules->archive($this->hash, $schedule->id);
        $this->expectException(ScheduleNotFoundException::class);
        $this->sets->create($this->hash, $schedule->id, 'S', 1, 10, 0);
    }

    public function testPatchDayAndMinutesBounds(): void
    {
        $schedule = $this->schedules->create($this->hash, 'P');
        $set = $this->sets->create($this->hash, $schedule->id, 'S', 1, 10, 0);
        $this->sets->patch($this->hash, $set->id, ['day_of_week' => 0]);
        $this->sets->patch($this->hash, $set->id, ['day_of_week' => 6]);
        $this->sets->patch($this->hash, $set->id, ['start_minutes' => 0]);
        $this->sets->patch($this->hash, $set->id, ['start_minutes' => 1439]);
        try {
            $this->sets->patch($this->hash, $set->id, ['day_of_week' => 7]);
            $this->fail('day');
        } catch (InvalidSetException) {
        }
        $this->expectException(InvalidSetException::class);
        $this->sets->patch($this->hash, $set->id, ['start_minutes' => 1440]);
    }

    public function testBlankSetNameOnCreate(): void
    {
        $schedule = $this->schedules->create($this->hash, 'P');
        $this->expectException(InvalidSetException::class);
        $this->sets->create($this->hash, $schedule->id, '  ', 1, 10, 0);
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
