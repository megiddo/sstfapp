<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use Sstf\Api\Domain\ExerciseLog;
use Sstf\Api\Domain\HistoryDay;
use Sstf\Api\Domain\HistoryFilters;
use Sstf\Api\Domain\HistoryGrouper;
use Sstf\Api\Domain\InvalidHistoryFilterException;
use Sstf\Api\Domain\LogNotFoundException;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Http\Controllers\ExportController;
use Sstf\Api\Http\Controllers\LogController;
use Sstf\Api\Http\FileResponder;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Http\Middleware\SessionAuth;
use Sstf\Api\Infrastructure\Sqlite\LogRepository;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Services\ExportService;
use Sstf\Api\Services\LogService;
use Sstf\Api\Tests\HttpTestCase;

#[CoversClass(LogController::class)]
#[CoversClass(ExportController::class)]
#[CoversClass(LogService::class)]
#[CoversClass(ExportService::class)]
#[CoversClass(LogRepository::class)]
#[CoversClass(UserDbFactory::class)]
#[CoversClass(HistoryGrouper::class)]
#[CoversClass(HistoryDay::class)]
#[CoversClass(HistoryFilters::class)]
#[CoversClass(InvalidHistoryFilterException::class)]
#[CoversClass(LogNotFoundException::class)]
#[CoversClass(ExerciseLog::class)]
#[CoversClass(UnauthenticatedException::class)]
#[CoversClass(FileResponder::class)]
#[CoversClass(JsonResponder::class)]
#[CoversClass(SessionAuth::class)]
final class HistoryExportTest extends HttpTestCase
{
    public function testLogsGroupByLocalDayAndExportContainsThoseRows(): void
    {
        $email = 'hist-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email, 'America/Chicago');
        $seeded = $this->seedEvening();

        $this->freezeAt('2026-08-19 18:40:00', 'America/Chicago');
        $first = $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 185,
            'reps' => 8,
        ]);
        $this->assertSame(200, $first->getStatusCode());

        $this->freezeAt('2026-08-20 18:40:00', 'America/Chicago');
        $second = $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['rowId'],
            'weight' => 60,
            'reps' => 10,
        ]);
        $this->assertSame(200, $second->getStatusCode());

        $this->request('PATCH', '/api/me', ['weight_unit' => 'kg']);

        $history = $this->request('GET', '/api/logs');
        $this->assertSame(200, $history->getStatusCode());
        $days = $this->json($history)['data']['days'];
        $this->assertCount(2, $days);
        $this->assertSame('2026-08-20', $days[0]['date']);
        $this->assertSame('2026-08-19', $days[1]['date']);
        $this->assertSame('Barbell Row', $days[0]['logs'][0]['exercise_name']);
        $this->assertSame('Evening', $days[0]['logs'][0]['set_name']);
        $this->assertEquals(60, $days[0]['logs'][0]['weight']);
        $this->assertSame('lb', $days[0]['logs'][0]['weight_unit']);
        $this->assertSame(10, $days[0]['logs'][0]['reps']);
        $this->assertSame('Bench Press', $days[1]['logs'][0]['exercise_name']);
        $this->assertSame('lb', $days[1]['logs'][0]['weight_unit']);
        $this->assertNotSame('kg', $days[1]['logs'][0]['weight_unit']);

        $export = $this->request('GET', '/api/export');
        $this->assertSame(200, $export->getStatusCode());
        $this->assertSame(FileResponder::SQLITE_TYPE, $export->getHeaderLine('Content-Type'));
        $this->assertSame(
            'attachment; filename="sstf-data.sqlite"',
            $export->getHeaderLine('Content-Disposition'),
        );
        $this->assertStringNotContainsString($email, $export->getHeaderLine('Content-Disposition'));
        $this->assertStringNotContainsString('password', strtolower($export->getHeaderLine('Content-Disposition')));

        $copy = $this->dataDir . '/downloaded.sqlite';
        file_put_contents($copy, (string) $export->getBody());
        $pdo = new PDO('sqlite:' . $copy);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->assertSame($email, $pdo->query('SELECT email FROM account WHERE id = 1')->fetchColumn());
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM logs')->fetchColumn());
        $units = $pdo->query('SELECT DISTINCT weight_unit FROM logs')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame(['lb'], $units);
    }

    public function testLogsAcceptFromToAndExerciseIdFilters(): void
    {
        $email = 'filt-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email, 'America/Chicago');
        $seeded = $this->seedEvening();

        $this->freezeAt('2026-08-19 18:40:00', 'America/Chicago');
        $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 185,
            'reps' => 8,
        ]);
        $this->freezeAt('2026-08-20 18:40:00', 'America/Chicago');
        $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['rowId'],
            'weight' => 60,
            'reps' => 10,
        ]);

        $from = $this->request('GET', '/api/logs?from=2026-08-20');
        $this->assertSame(200, $from->getStatusCode());
        $days = $this->json($from)['data']['days'];
        $this->assertCount(1, $days);
        $this->assertSame('2026-08-20', $days[0]['date']);
        $this->assertSame('Barbell Row', $days[0]['logs'][0]['exercise_name']);

        $to = $this->json($this->request('GET', '/api/logs?to=2026-08-19'))['data']['days'];
        $this->assertCount(1, $to);
        $this->assertSame('2026-08-19', $to[0]['date']);

        $exercise = $this->json($this->request('GET', '/api/logs?exercise_id=' . $seeded['benchId']))['data']['days'];
        $this->assertCount(1, $exercise);
        $this->assertSame('Bench Press', $exercise[0]['logs'][0]['exercise_name']);

        $range = $this->json($this->request(
            'GET',
            '/api/logs?from=2026-08-19&to=2026-08-20&exercise_id=' . $seeded['rowId'],
        ))['data']['days'];
        $this->assertCount(1, $range);
        $this->assertSame('Barbell Row', $range[0]['logs'][0]['exercise_name']);

        $bad = $this->request('GET', '/api/logs?from=not-a-day');
        $this->assertSame(400, $bad->getStatusCode());
        $this->assertSame('invalid_request', $this->json($bad)['error']['code']);
        $this->assertSame('Invalid history filter', $this->json($bad)['error']['message']);
    }

    public function testExportIsOnlyTheSignedInUserFile(): void
    {
        $alice = 'alice-' . bin2hex(random_bytes(4)) . '@example.com';
        $bob = 'bob-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($alice, 'UTC');
        $seeded = $this->seedEvening();
        $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 200,
            'reps' => 3,
        ]);

        $this->request('POST', '/api/auth/logout', []);
        $this->signIn($bob, 'UTC');

        $export = $this->request('GET', '/api/export');
        $this->assertSame(200, $export->getStatusCode());
        $copy = $this->dataDir . '/bob.sqlite';
        file_put_contents($copy, (string) $export->getBody());
        $pdo = new PDO('sqlite:' . $copy);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->assertSame($bob, $pdo->query('SELECT email FROM account WHERE id = 1')->fetchColumn());
        $this->assertNotSame($alice, $pdo->query('SELECT email FROM account WHERE id = 1')->fetchColumn());
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM logs')->fetchColumn());
    }

    public function testLogsAndExportRequireSession(): void
    {
        $logs = $this->request('GET', '/api/logs');
        $this->assertSame(401, $logs->getStatusCode());
        $this->assertSame('unauthenticated', $this->json($logs)['error']['code']);

        $export = $this->request('GET', '/api/export');
        $this->assertSame(401, $export->getStatusCode());
        $this->assertSame('unauthenticated', $this->json($export)['error']['code']);
        $this->assertSame('application/json', $export->getHeaderLine('Content-Type'));
        $this->assertSame('', $export->getHeaderLine('Content-Disposition'));
    }

    public function testLogsCanBePatchedAndDeleted(): void
    {
        $email = 'edit-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email, 'America/Chicago');
        $seeded = $this->seedEvening();

        $this->freezeAt('2026-08-19 18:40:00', 'America/Chicago');
        $created = $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 185,
            'reps' => 8,
        ]);
        $this->assertSame(200, $created->getStatusCode());
        $logId = $this->json($created)['data']['id'];

        $patched = $this->request('PATCH', '/api/logs/' . $logId, [
            'weight' => 190.5,
            'reps' => 6,
        ]);
        $this->assertSame(200, $patched->getStatusCode());
        $this->assertEquals(190.5, $this->json($patched)['data']['weight']);
        $this->assertSame(6, $this->json($patched)['data']['reps']);
        $this->assertSame('Bench Press', $this->json($patched)['data']['exercise_name']);

        $history = $this->json($this->request('GET', '/api/logs'))['data']['days'];
        $this->assertCount(1, $history);
        $this->assertEquals(190.5, $history[0]['logs'][0]['weight']);
        $this->assertSame(6, $history[0]['logs'][0]['reps']);

        $this->assertSame(400, $this->request('PATCH', '/api/logs/' . $logId, [
            'weight' => -1,
            'reps' => 1,
        ])->getStatusCode());
        $this->assertSame(400, $this->request('PATCH', '/api/logs/' . $logId, [
            'weight' => 1,
        ])->getStatusCode());
        $this->assertSame(404, $this->request('PATCH', '/api/logs/99999', [
            'weight' => 1,
            'reps' => 1,
        ])->getStatusCode());
        $this->assertSame(404, $this->request('PATCH', '/api/logs/abc', [
            'weight' => 1,
            'reps' => 1,
        ])->getStatusCode());

        $deleted = $this->request('DELETE', '/api/logs/' . $logId, []);
        $this->assertSame(200, $deleted->getStatusCode());
        $this->assertTrue($this->json($deleted)['data']['ok']);
        $this->assertSame([], $this->json($this->request('GET', '/api/logs'))['data']['days']);
        $this->assertSame(404, $this->request('DELETE', '/api/logs/' . $logId, [])->getStatusCode());
    }

    /**
     * @return array{eveningId: int, benchId: int, rowId: int}
     */
    private function seedEvening(): array
    {
        $schedule = $this->json($this->request('POST', '/api/schedules', ['name' => 'Hypertrophy']))['data'];
        $evening = $this->json($this->request('POST', '/api/schedules/' . $schedule['id'] . '/sets', [
            'name' => 'Evening',
            'day_of_week' => 3,
            'start_minutes' => 1080,
        ]))['data'];
        $catalog = $this->json($this->request('GET', '/api/exercises'))['data']['exercises'];
        $byName = [];
        foreach ($catalog as $row) {
            $byName[$row['name']] = $row;
        }
        $this->request('PUT', '/api/sets/' . $evening['id'] . '/exercises', [
            'exercises' => [
                ['global_exercise_id' => $byName['Bench Press']['id']],
                ['global_exercise_id' => $byName['Barbell Row']['id']],
            ],
        ]);

        return [
            'eveningId' => $evening['id'],
            'benchId' => $byName['Bench Press']['id'],
            'rowId' => $byName['Barbell Row']['id'],
        ];
    }
}
