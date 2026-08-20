<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Http\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Sstf\Api\Domain\EmailKey;
use Sstf\Api\Domain\ExerciseLog;
use Sstf\Api\Domain\ExerciseNotOnSetException;
use Sstf\Api\Domain\InvalidLogException;
use Sstf\Api\Domain\LogPrefill;
use Sstf\Api\Domain\Schedule;
use Sstf\Api\Domain\SetExercise;
use Sstf\Api\Domain\SetNotFoundException;
use Sstf\Api\Domain\TrainingSet;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Domain\WorkoutCurrent;
use Sstf\Api\Domain\WorkoutSetSummary;
use Sstf\Api\Http\Controllers\LogController;
use Sstf\Api\Http\Controllers\WorkoutController;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Infrastructure\Sqlite\ExerciseRepository;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\LogRepository;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\ScheduleRepository;
use Sstf\Api\Infrastructure\Sqlite\SetRepository;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;
use Sstf\Api\Services\LogService;
use Sstf\Api\Services\ScheduleService;
use Sstf\Api\Services\SetService;
use Sstf\Api\Services\WorkoutService;
use Sstf\Api\Tests\Fakes\FakeClock;

#[CoversClass(WorkoutController::class)]
#[CoversClass(LogController::class)]
#[CoversClass(WorkoutService::class)]
#[CoversClass(LogService::class)]
#[CoversClass(LogRepository::class)]
#[CoversClass(WorkoutCurrent::class)]
#[CoversClass(WorkoutSetSummary::class)]
#[CoversClass(ExerciseLog::class)]
#[CoversClass(LogPrefill::class)]
#[CoversClass(InvalidLogException::class)]
#[CoversClass(ExerciseNotOnSetException::class)]
#[CoversClass(SetNotFoundException::class)]
#[CoversClass(UnauthenticatedException::class)]
#[CoversClass(Schedule::class)]
#[CoversClass(TrainingSet::class)]
#[CoversClass(SetExercise::class)]
#[CoversClass(JsonResponder::class)]
final class WorkoutLogControllerTest extends TestCase
{
    private string $tmp;

    private string $hash;

    private WorkoutController $workouts;

    private LogController $logs;

    private ScheduleService $schedules;

    private SetService $sets;

    private LogRepository $logRepo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-wo-ctl-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/users', 0700, true);
        $this->hash = EmailKey::fromEmail('wo@example.com')->hash();
        $root = dirname(__DIR__, 4);
        $clock = new FakeClock((new \DateTimeImmutable('2026-08-19 18:40:00', new \DateTimeZone('America/Chicago')))->getTimestamp());
        $users = new UserDbFactory($this->tmp . '/users', new Migrator(), $root . '/migrations/user');
        $global = new GlobalDb($this->tmp . '/global.sqlite', new Migrator(), $root . '/migrations/global');
        $directory = new UserDirectory($users, $global, $clock);
        $directory->provisionGoogleUser(
            EmailKey::fromEmail('wo@example.com'),
            'wo@example.com',
            'sub-wo',
            'America/Chicago',
        );
        $this->schedules = new ScheduleService(new ScheduleRepository($users, $clock));
        $catalog = new ExerciseRepository($global, $clock);
        $this->sets = new SetService(new SetRepository($users, $clock), $catalog);
        $this->logRepo = new LogRepository($users, $clock);
        $workoutService = new WorkoutService(
            $clock,
            $directory,
            new ScheduleRepository($users, $clock),
            new SetRepository($users, $clock),
            $this->logRepo,
        );
        $logService = new LogService(
            $directory,
            new ScheduleRepository($users, $clock),
            new SetRepository($users, $clock),
            $this->logRepo,
        );
        $this->workouts = new WorkoutController($workoutService);
        $this->logs = new LogController($logService);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testControllersGuardAuthAndValidateBodies(): void
    {
        $factory = new ServerRequestFactory();
        $unauth = $factory->createServerRequest('GET', '/api/workout/current');
        $this->assertSame(401, $this->workouts->current($unauth, new Response())->getStatusCode());
        $this->assertSame(401, $this->workouts->sets($unauth, new Response())->getStatusCode());
        $this->assertSame(401, $this->logs->index($unauth, new Response())->getStatusCode());
        $this->assertSame(401, $this->logs->create($unauth->withParsedBody([
            'set_id' => 1,
            'global_exercise_id' => 1,
            'weight' => 1,
            'reps' => 1,
        ]), new Response())->getStatusCode());

        $missingAccount = $factory->createServerRequest('GET', '/api/workout/current')
            ->withAttribute('email_hash', str_repeat('11', 16));
        $this->assertSame(401, $this->workouts->current($missingAccount, new Response())->getStatusCode());
        $this->assertSame(401, $this->workouts->sets($missingAccount, new Response())->getStatusCode());
        $this->assertSame(401, $this->logs->index($missingAccount, new Response())->getStatusCode());
        $this->assertSame(401, $this->logs->create($missingAccount->withParsedBody([
            'set_id' => 1,
            'global_exercise_id' => 1,
            'weight' => 1,
            'reps' => 1,
        ]), new Response())->getStatusCode());

        $authed = $factory->createServerRequest('GET', '/api/workout/current')
            ->withAttribute('email_hash', $this->hash);
        $this->assertSame(404, $this->workouts->current(
            $authed->withQueryParams(['set_id' => '0']),
            new Response(),
        )->getStatusCode());
        $this->assertSame(404, $this->workouts->current(
            $authed->withQueryParams(['set_id' => 0]),
            new Response(),
        )->getStatusCode());
        $okBlank = $this->workouts->current($authed->withQueryParams(['set_id' => null]), new Response());
        $this->assertSame(200, $okBlank->getStatusCode());

        $this->assertSame(400, $this->logs->create($authed->withParsedBody(null), new Response())->getStatusCode());
        $this->assertSame(400, $this->logs->create($authed->withParsedBody([
            'set_id' => '1',
            'global_exercise_id' => 1,
            'weight' => 1,
            'reps' => 1,
        ]), new Response())->getStatusCode());
        $this->assertSame(400, $this->logs->create($authed->withParsedBody([
            'set_id' => 0,
            'global_exercise_id' => 1,
            'weight' => 1,
            'reps' => 1,
        ]), new Response())->getStatusCode());
        $this->assertSame(400, $this->logs->create($authed->withParsedBody([
            'set_id' => 1,
            'global_exercise_id' => 1,
            'weight' => '185',
            'reps' => 1,
        ]), new Response())->getStatusCode());
        $this->assertSame(400, $this->logs->create($authed->withParsedBody([
            'set_id' => 1,
            'global_exercise_id' => 1,
            'weight' => 1,
            'reps' => 1.5,
        ]), new Response())->getStatusCode());
        $this->assertSame(400, $this->logs->create($authed->withParsedBody([
            'set_id' => 1,
            'global_exercise_id' => 1,
            'weight' => 1,
            'reps' => 1,
            'notes' => 12,
        ]), new Response())->getStatusCode());
        $this->assertSame(400, $this->logs->create($authed->withParsedBody([
            'set_id' => 1,
            'global_exercise_id' => 1,
            'weight' => INF,
            'reps' => 1,
        ]), new Response())->getStatusCode());

        $schedule = $this->schedules->create($this->hash, 'Hypertrophy');
        $set = $this->sets->create($this->hash, $schedule->id, 'Evening', 3, 1080, 0);
        $replaced = $this->sets->replaceExercises($this->hash, $set->id, [$this->firstExerciseId()]);
        $exerciseId = $replaced->exercises[0]->globalExerciseId;
        $this->assertNotNull($exerciseId);

        $intOverride = $this->workouts->current(
            $authed->withQueryParams(['set_id' => $set->id]),
            new Response(),
        );
        $this->assertSame(200, $intOverride->getStatusCode());

        $created = $this->logs->create($authed->withParsedBody([
            'set_id' => $set->id,
            'global_exercise_id' => $exerciseId,
            'weight' => 12.5,
            'reps' => 3,
            'notes' => null,
        ]), new Response());
        $this->assertSame(200, $created->getStatusCode());

        $this->expectException(\RuntimeException::class);
        $this->logRepo->getById($this->hash, 99999);
    }

    private function firstExerciseId(): int
    {
        $root = dirname(__DIR__, 4);
        $global = new GlobalDb($this->tmp . '/global.sqlite', new Migrator(), $root . '/migrations/global');
        $catalog = new ExerciseRepository($global, new FakeClock(1));
        $list = $catalog->search(null);
        $this->assertNotSame([], $list);

        return $list[0]->id;
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
