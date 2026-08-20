<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Http\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Sstf\Api\Domain\ClockInterface;
use Sstf\Api\Domain\Exercise;
use Sstf\Api\Domain\SystemClock;
use Sstf\Api\Http\Controllers\ExerciseController;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Infrastructure\Sqlite\ExerciseRepository;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\LogRepository;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Services\ExerciseService;
use Sstf\Api\Services\SuggestedExerciseService;

#[CoversClass(ExerciseController::class)]
#[CoversClass(ExerciseService::class)]
#[CoversClass(SuggestedExerciseService::class)]
#[CoversClass(ExerciseRepository::class)]
#[CoversClass(Exercise::class)]
#[CoversClass(JsonResponder::class)]
final class ExerciseControllerTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-ex-ctl-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testIndexAndCreateGuardAuthAndValidateBody(): void
    {
        $controller = $this->controller();
        $factory = new ServerRequestFactory();

        $unauthList = $controller->index(
            $factory->createServerRequest('GET', '/api/exercises'),
            new Response(),
        );
        $this->assertSame(401, $unauthList->getStatusCode());

        $unauthCreate = $controller->create(
            $factory->createServerRequest('POST', '/api/exercises')->withParsedBody(['name' => 'X']),
            new Response(),
        );
        $this->assertSame(401, $unauthCreate->getStatusCode());

        $authed = $factory->createServerRequest('POST', '/api/exercises')
            ->withAttribute('email_hash', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');

        $notArray = $controller->create($authed->withParsedBody(null), new Response());
        $this->assertSame(400, $notArray->getStatusCode());

        $notString = $controller->create($authed->withParsedBody(['name' => 12]), new Response());
        $this->assertSame(400, $notString->getStatusCode());

        $badMuscle = $controller->create(
            $authed->withParsedBody(['name' => 'Ok', 'muscle_group' => 1]),
            new Response(),
        );
        $this->assertSame(400, $badMuscle->getStatusCode());

        $badEquipment = $controller->create(
            $authed->withParsedBody(['name' => 'Ok', 'equipment' => []]),
            new Response(),
        );
        $this->assertSame(400, $badEquipment->getStatusCode());

        $badNotes = $controller->create(
            $authed->withParsedBody(['name' => 'Ok', 'notes' => 3]),
            new Response(),
        );
        $this->assertSame(400, $badNotes->getStatusCode());

        $blank = $controller->create($authed->withParsedBody(['name' => '   ']), new Response());
        $this->assertSame(400, $blank->getStatusCode());
        $this->assertStringContainsString('invalid_request', (string) $blank->getBody());

        $created = $controller->create(
            $authed->withParsedBody([
                'name' => 'Custom Fly',
                'muscle_group' => 'Chest',
                'equipment' => null,
                'notes' => '  ',
            ]),
            new Response(),
        );
        $this->assertSame(200, $created->getStatusCode());
        $this->assertStringContainsString('Custom Fly', (string) $created->getBody());

        $duplicate = $controller->create(
            $authed->withParsedBody(['name' => 'custom fly']),
            new Response(),
        );
        $this->assertSame(409, $duplicate->getStatusCode());

        $list = $controller->index(
            $factory->createServerRequest('GET', '/api/exercises')
                ->withAttribute('email_hash', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
                ->withQueryParams(['q' => 'custom']),
            new Response(),
        );
        $this->assertSame(200, $list->getStatusCode());
        $this->assertStringContainsString('Custom Fly', (string) $list->getBody());

        $suggestedUnauth = $controller->suggested(
            $factory->createServerRequest('GET', '/api/exercises/suggested'),
            new Response(),
        );
        $this->assertSame(401, $suggestedUnauth->getStatusCode());

        $arrayQ = $controller->index(
            $factory->createServerRequest('GET', '/api/exercises')
                ->withAttribute('email_hash', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
                ->withQueryParams(['q' => ['x']]),
            new Response(),
        );
        $this->assertSame(200, $arrayQ->getStatusCode());
        $decoded = json_decode((string) $arrayQ->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(15, count($decoded['data']['exercises']));
    }

    private function controller(?ClockInterface $clock = null): ExerciseController
    {
        $root = dirname(__DIR__, 4);
        $global = new GlobalDb(
            $this->tmp . '/global.sqlite',
            new Migrator(),
            $root . '/migrations/global',
        );
        mkdir($this->tmp . '/users', 0700, true);
        $users = new UserDbFactory($this->tmp . '/users', new Migrator(), $root . '/migrations/user');

        return new ExerciseController(
            new ExerciseService(new ExerciseRepository($global, $clock ?? new SystemClock())),
            new SuggestedExerciseService(new LogRepository($users, $clock ?? new SystemClock())),
        );
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
