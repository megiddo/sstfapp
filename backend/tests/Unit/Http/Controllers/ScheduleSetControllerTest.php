<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Http\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Sstf\Api\Domain\Schedule;
use Sstf\Api\Domain\SetExercise;
use Sstf\Api\Domain\TrainingSet;
use Sstf\Api\Http\Controllers\ScheduleController;
use Sstf\Api\Http\Controllers\SetController;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Infrastructure\Sqlite\ExerciseRepository;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\ScheduleRepository;
use Sstf\Api\Infrastructure\Sqlite\SetRepository;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Services\ScheduleService;
use Sstf\Api\Services\SetService;
use Sstf\Api\Tests\Fakes\FakeClock;

#[CoversClass(ScheduleController::class)]
#[CoversClass(SetController::class)]
#[CoversClass(ScheduleService::class)]
#[CoversClass(SetService::class)]
#[CoversClass(ScheduleRepository::class)]
#[CoversClass(SetRepository::class)]
#[CoversClass(ExerciseRepository::class)]
#[CoversClass(Schedule::class)]
#[CoversClass(TrainingSet::class)]
#[CoversClass(SetExercise::class)]
#[CoversClass(JsonResponder::class)]
final class ScheduleSetControllerTest extends TestCase
{
    private string $tmp;

    private string $hash;

    private ScheduleController $schedules;

    private SetController $sets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-sched-ctl-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/users', 0700, true);
        $this->hash = str_repeat('cd', 16);
        $root = dirname(__DIR__, 4);
        $clock = new FakeClock(1_700_000_000);
        $users = new UserDbFactory($this->tmp . '/users', new Migrator(), $root . '/migrations/user');
        $users->open($this->hash);
        $global = new GlobalDb($this->tmp . '/global.sqlite', new Migrator(), $root . '/migrations/global');
        $catalog = new ExerciseRepository($global, $clock);
        $scheduleService = new ScheduleService(new ScheduleRepository($users, $clock));
        $setService = new SetService(new SetRepository($users, $clock), $catalog);
        $this->schedules = new ScheduleController($scheduleService, $setService);
        $this->sets = new SetController($setService);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testControllersGuardAuthValidateBodiesAndIds(): void
    {
        $factory = new ServerRequestFactory();
        $unauth = $factory->createServerRequest('GET', '/api/schedules');

        $this->assertSame(401, $this->schedules->index($unauth, new Response())->getStatusCode());
        $this->assertSame(401, $this->schedules->create($unauth->withParsedBody(['name' => 'X']), new Response())->getStatusCode());
        $this->assertSame(401, $this->schedules->patch($unauth, new Response(), ['id' => '1'])->getStatusCode());
        $this->assertSame(401, $this->schedules->activate($unauth, new Response(), ['id' => '1'])->getStatusCode());
        $this->assertSame(401, $this->schedules->delete($unauth, new Response(), ['id' => '1'])->getStatusCode());
        $this->assertSame(401, $this->schedules->listSets($unauth, new Response(), ['id' => '1'])->getStatusCode());
        $this->assertSame(401, $this->schedules->createSet($unauth->withParsedBody(['name' => 'X']), new Response(), ['id' => '1'])->getStatusCode());
        $this->assertSame(401, $this->sets->patch($unauth, new Response(), ['id' => '1'])->getStatusCode());
        $this->assertSame(401, $this->sets->delete($unauth, new Response(), ['id' => '1'])->getStatusCode());
        $this->assertSame(401, $this->sets->replaceExercises($unauth, new Response(), ['id' => '1'])->getStatusCode());

        $authedGet = $factory->createServerRequest('GET', '/api/schedules')->withAttribute('email_hash', $this->hash);
        $empty = $this->schedules->index($authedGet, new Response());
        $this->assertSame(200, $empty->getStatusCode());
        $this->assertSame(['schedules' => []], json_decode((string) $empty->getBody(), true, 512, JSON_THROW_ON_ERROR)['data']);

        $authedPost = $factory->createServerRequest('POST', '/api/schedules')
            ->withAttribute('email_hash', $this->hash);
        $this->assertSame(400, $this->schedules->create($authedPost->withParsedBody(null), new Response())->getStatusCode());
        $this->assertSame(400, $this->schedules->create($authedPost->withParsedBody(['name' => 1]), new Response())->getStatusCode());
        $this->assertSame(400, $this->schedules->create($authedPost->withParsedBody(['name' => '  ']), new Response())->getStatusCode());

        $created = $this->schedules->create($authedPost->withParsedBody(['name' => 'Plan']), new Response());
        $this->assertSame(200, $created->getStatusCode());
        $id = json_decode((string) $created->getBody(), true, 512, JSON_THROW_ON_ERROR)['data']['id'];

        $patchReq = $factory->createServerRequest('PATCH', '/api/schedules/' . $id)
            ->withAttribute('email_hash', $this->hash);
        $this->assertSame(404, $this->schedules->patch($patchReq->withParsedBody(['name' => 'X']), new Response(), ['id' => '0'])->getStatusCode());
        $this->assertSame(404, $this->schedules->patch($patchReq->withParsedBody(['name' => 'X']), new Response(), ['id' => 'abc'])->getStatusCode());
        $this->assertSame(400, $this->schedules->patch($patchReq->withParsedBody(null), new Response(), ['id' => (string) $id])->getStatusCode());
        $this->assertSame(400, $this->schedules->patch($patchReq->withParsedBody(['name' => 3]), new Response(), ['id' => (string) $id])->getStatusCode());
        $renamed = $this->schedules->patch($patchReq->withParsedBody(['name' => 'Renamed']), new Response(), ['id' => $id]);
        $this->assertSame(200, $renamed->getStatusCode());

        $this->assertSame(404, $this->schedules->activate($authedGet, new Response(), ['id' => '-1'])->getStatusCode());
        $this->assertSame(404, $this->schedules->delete($authedGet, new Response(), ['id' => 'nope'])->getStatusCode());
        $this->assertSame(404, $this->schedules->listSets($authedGet, new Response(), ['id' => '00'])->getStatusCode());

        $createSet = $factory->createServerRequest('POST', '/api/schedules/' . $id . '/sets')
            ->withAttribute('email_hash', $this->hash);
        $this->assertSame(404, $this->schedules->createSet($createSet->withParsedBody(['name' => 'S']), new Response(), ['id' => 'x'])->getStatusCode());
        $this->assertSame(400, $this->schedules->createSet($createSet->withParsedBody(null), new Response(), ['id' => (string) $id])->getStatusCode());
        $this->assertSame(400, $this->schedules->createSet($createSet->withParsedBody(['name' => 1]), new Response(), ['id' => (string) $id])->getStatusCode());
        $this->assertSame(400, $this->schedules->createSet($createSet->withParsedBody([
            'name' => 'S',
            'day_of_week' => '3',
            'start_minutes' => 10,
        ]), new Response(), ['id' => (string) $id])->getStatusCode());
        $this->assertSame(400, $this->schedules->createSet($createSet->withParsedBody([
            'name' => 'S',
            'day_of_week' => 3,
            'start_minutes' => 10.5,
        ]), new Response(), ['id' => (string) $id])->getStatusCode());
        $this->assertSame(400, $this->schedules->createSet($createSet->withParsedBody([
            'name' => 'S',
            'day_of_week' => 3,
            'start_minutes' => 10,
            'sort_order' => '1',
        ]), new Response(), ['id' => (string) $id])->getStatusCode());

        $okSet = $this->schedules->createSet($createSet->withParsedBody([
            'name' => 'Evening',
            'day_of_week' => 3,
            'start_minutes' => 1080,
        ]), new Response(), ['id' => (string) $id]);
        $this->assertSame(200, $okSet->getStatusCode());
        $setId = json_decode((string) $okSet->getBody(), true, 512, JSON_THROW_ON_ERROR)['data']['id'];

        $setPatch = $factory->createServerRequest('PATCH', '/api/sets/' . $setId)
            ->withAttribute('email_hash', $this->hash);
        $this->assertSame(404, $this->sets->patch($setPatch->withParsedBody(['name' => 'X']), new Response(), ['id' => 'bad'])->getStatusCode());
        $this->assertSame(400, $this->sets->patch($setPatch->withParsedBody(null), new Response(), ['id' => (string) $setId])->getStatusCode());
        $this->assertSame(400, $this->sets->patch($setPatch->withParsedBody(['name' => 1]), new Response(), ['id' => (string) $setId])->getStatusCode());
        $this->assertSame(400, $this->sets->patch($setPatch->withParsedBody(['day_of_week' => '1']), new Response(), ['id' => (string) $setId])->getStatusCode());
        $this->assertSame(400, $this->sets->patch($setPatch->withParsedBody(['start_minutes' => true]), new Response(), ['id' => (string) $setId])->getStatusCode());
        $this->assertSame(400, $this->sets->patch($setPatch->withParsedBody(['sort_order' => 1.2]), new Response(), ['id' => (string) $setId])->getStatusCode());

        $put = $factory->createServerRequest('PUT', '/api/sets/' . $setId . '/exercises')
            ->withAttribute('email_hash', $this->hash);
        $this->assertSame(404, $this->sets->replaceExercises($put->withParsedBody(['exercises' => []]), new Response(), ['id' => '0'])->getStatusCode());
        $this->assertSame(400, $this->sets->replaceExercises($put->withParsedBody(null), new Response(), ['id' => (string) $setId])->getStatusCode());
        $this->assertSame(400, $this->sets->replaceExercises($put->withParsedBody(['exercises' => [['global_exercise_id' => 0]]]), new Response(), ['id' => (string) $setId])->getStatusCode());
        $this->assertSame(400, $this->sets->replaceExercises($put->withParsedBody(['exercises' => [['global_exercise_id' => '1']]]), new Response(), ['id' => (string) $setId])->getStatusCode());

        $this->assertSame(404, $this->sets->delete($authedGet, new Response(), ['id' => 'no'])->getStatusCode());
        $intDelete = $this->sets->delete($authedGet, new Response(), ['id' => $setId]);
        $this->assertSame(200, $intDelete->getStatusCode());
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
