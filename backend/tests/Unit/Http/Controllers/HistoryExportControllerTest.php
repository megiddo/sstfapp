<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Http\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Sstf\Api\Domain\RepoKey;
use Sstf\Api\Domain\ExerciseLog;
use Sstf\Api\Domain\HistoryDay;
use Sstf\Api\Domain\HistoryFilters;
use Sstf\Api\Domain\HistoryGrouper;
use Sstf\Api\Domain\InvalidHistoryFilterException;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Http\Controllers\ExportController;
use Sstf\Api\Http\Controllers\LogController;
use Sstf\Api\Http\FileResponder;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Infrastructure\Sqlite\ExerciseRepository;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\LogRepository;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\ScheduleRepository;
use Sstf\Api\Infrastructure\Sqlite\SetRepository;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;
use Sstf\Api\Services\ExportService;
use Sstf\Api\Services\LogService;
use Sstf\Api\Services\ScheduleService;
use Sstf\Api\Services\SetService;
use Sstf\Api\Tests\Fakes\FakeClock;

#[CoversClass(LogController::class)]
#[CoversClass(ExportController::class)]
#[CoversClass(LogService::class)]
#[CoversClass(ExportService::class)]
#[CoversClass(LogRepository::class)]
#[CoversClass(HistoryGrouper::class)]
#[CoversClass(HistoryDay::class)]
#[CoversClass(HistoryFilters::class)]
#[CoversClass(InvalidHistoryFilterException::class)]
#[CoversClass(ExerciseLog::class)]
#[CoversClass(UnauthenticatedException::class)]
#[CoversClass(FileResponder::class)]
#[CoversClass(JsonResponder::class)]
final class HistoryExportControllerTest extends TestCase
{
    private string $tmp;

    private string $hash;

    private UserDbFactory $users;

    private FakeClock $clock;

    private LogController $logs;

    private ExportController $export;

    private ScheduleService $schedules;

    private SetService $sets;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-hist-ctl-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/users', 0700, true);
        $this->hash = RepoKey::google('hist@example.com')->hash();
        $root = dirname(__DIR__, 4);
        $this->clock = new FakeClock(
            (new \DateTimeImmutable('2026-08-19 18:40:00', new \DateTimeZone('America/Chicago')))->getTimestamp(),
        );
        $this->users = new UserDbFactory($this->tmp . '/users', new Migrator(), $root . '/migrations/user');
        $global = new GlobalDb($this->tmp . '/global.sqlite', new Migrator(), $root . '/migrations/global');
        $directory = new UserDirectory($this->users, $global, $this->clock);
        $directory->provisionGoogleUser(
            $this->hash,
            'hist@example.com',
            'hist@example.com',
            'sub-hist',
            'America/Chicago',
        );
        $this->schedules = new ScheduleService(new ScheduleRepository($this->users, $this->clock));
        $catalog = new ExerciseRepository($global, $this->clock);
        $this->sets = new SetService(new SetRepository($this->users, $this->clock), $catalog);
        $logRepo = new LogRepository($this->users, $this->clock);
        $this->logs = new LogController(new LogService(
            $directory,
            new ScheduleRepository($this->users, $this->clock),
            new SetRepository($this->users, $this->clock),
            $logRepo,
        ));
        $this->export = new ExportController(new ExportService($directory, $this->users));
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testIndexAndExportGuardAuthAndReturnHistory(): void
    {
        $factory = new ServerRequestFactory();
        $unauth = $factory->createServerRequest('GET', '/api/logs');
        $this->assertSame(401, $this->logs->index($unauth, new Response())->getStatusCode());
        $this->assertSame(401, $this->export->download($unauth, new Response())->getStatusCode());

        $missing = $factory->createServerRequest('GET', '/api/logs')
            ->withAttribute('email_hash', str_repeat('11', 16));
        $this->assertSame(401, $this->logs->index($missing, new Response())->getStatusCode());
        $this->assertSame(401, $this->export->download($missing, new Response())->getStatusCode());

        $blank = $factory->createServerRequest('GET', '/api/logs')->withAttribute('email_hash', '');
        $this->assertSame(401, $this->logs->index($blank, new Response())->getStatusCode());
        $this->assertSame(401, $this->export->download($blank, new Response())->getStatusCode());

        $authed = $factory->createServerRequest('GET', '/api/logs')->withAttribute('email_hash', $this->hash);
        $empty = $this->logs->index($authed, new Response());
        $this->assertSame(200, $empty->getStatusCode());
        $emptyBody = json_decode((string) $empty->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([], $emptyBody['data']['days']);

        $schedule = $this->schedules->create($this->hash, 'Hypertrophy');
        $set = $this->sets->create($this->hash, $schedule->id, 'Evening', 3, 1080, 0);
        $exerciseId = $this->sets->replaceExercises($this->hash, $set->id, [$this->firstExerciseId()])
            ->exercises[0]->globalExerciseId;
        $this->assertNotNull($exerciseId);

        $this->logs->create($authed->withParsedBody([
            'set_id' => $set->id,
            'global_exercise_id' => $exerciseId,
            'weight' => 185,
            'reps' => 8,
        ]), new Response());
        $this->clock->setTimestamp(
            (new \DateTimeImmutable('2026-08-20 18:40:00', new \DateTimeZone('America/Chicago')))->getTimestamp(),
        );
        $this->logs->create($authed->withParsedBody([
            'set_id' => $set->id,
            'global_exercise_id' => $exerciseId,
            'weight' => 80,
            'reps' => 6,
        ]), new Response());

        $history = $this->logs->index($authed, new Response());
        $this->assertSame(200, $history->getStatusCode());
        $body = json_decode((string) $history->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(2, $body['data']['days']);
        $this->assertSame('2026-08-20', $body['data']['days'][0]['date']);
        $this->assertSame('2026-08-19', $body['data']['days'][1]['date']);
        $this->assertSame('lb', $body['data']['days'][0]['logs'][0]['weight_unit']);

        $file = $this->export->download($authed, new Response());
        $this->assertSame(200, $file->getStatusCode());
        $this->assertSame(FileResponder::SQLITE_TYPE, $file->getHeaderLine('Content-Type'));
        $this->assertSame(
            'attachment; filename="sstf-data.sqlite"',
            $file->getHeaderLine('Content-Disposition'),
        );
        $this->assertStringNotContainsString('hist@example.com', $file->getHeaderLine('Content-Disposition'));
        $bytes = (string) $file->getBody();
        $this->assertStringStartsWith('SQLite format 3', $bytes);

        $copy = $this->tmp . '/export-copy.sqlite';
        file_put_contents($copy, $bytes);
        $pdo = new \PDO('sqlite:' . $copy);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->assertSame('hist@example.com', $pdo->query('SELECT email FROM account WHERE id = 1')->fetchColumn());
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM logs')->fetchColumn());
    }

    public function testIndexAppliesHistoryFilters(): void
    {
        $factory = new ServerRequestFactory();
        $authed = $factory->createServerRequest('GET', '/api/logs')->withAttribute('email_hash', $this->hash);
        $schedule = $this->schedules->create($this->hash, 'Hypertrophy');
        $set = $this->sets->create($this->hash, $schedule->id, 'Evening', 3, 1080, 0);
        $exerciseId = $this->sets->replaceExercises($this->hash, $set->id, [$this->firstExerciseId()])
            ->exercises[0]->globalExerciseId;
        $this->assertNotNull($exerciseId);
        $this->logs->create($authed->withParsedBody([
            'set_id' => $set->id,
            'global_exercise_id' => $exerciseId,
            'weight' => 185,
            'reps' => 8,
        ]), new Response());

        $day = $this->logs->index($authed->withQueryParams([
            'from' => '2026-08-19',
            'to' => '2026-08-19',
            'exercise_id' => (string) $exerciseId,
        ]), new Response());
        $this->assertSame(200, $day->getStatusCode());
        $body = json_decode((string) $day->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $body['data']['days']);
        $this->assertSame('2026-08-19', $body['data']['days'][0]['date']);

        $none = $this->logs->index($authed->withQueryParams(['from' => '2026-08-21']), new Response());
        $this->assertSame([], json_decode((string) $none->getBody(), true, 512, JSON_THROW_ON_ERROR)['data']['days']);

        $missingEx = $this->logs->index($authed->withQueryParams(['exercise_id' => '99999']), new Response());
        $this->assertSame([], json_decode((string) $missingEx->getBody(), true, 512, JSON_THROW_ON_ERROR)['data']['days']);

        $bad = $this->logs->index($authed->withQueryParams(['from' => 'nope']), new Response());
        $this->assertSame(400, $bad->getStatusCode());
        $this->assertSame('invalid_request', json_decode((string) $bad->getBody(), true, 512, JSON_THROW_ON_ERROR)['error']['code']);
    }

    public function testExportRejectsInvalidHashNames(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/export')
            ->withAttribute('email_hash', 'not-a-valid-hash');
        $this->assertSame(401, $this->export->download($request, new Response())->getStatusCode());
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
