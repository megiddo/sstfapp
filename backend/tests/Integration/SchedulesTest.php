<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use Sstf\Api\Domain\CatalogExerciseNotFoundException;
use Sstf\Api\Domain\InvalidScheduleException;
use Sstf\Api\Domain\InvalidSetException;
use Sstf\Api\Domain\Schedule;
use Sstf\Api\Domain\ScheduleNotFoundException;
use Sstf\Api\Domain\SetExercise;
use Sstf\Api\Domain\SetNotFoundException;
use Sstf\Api\Domain\TrainingSet;
use Sstf\Api\Http\Controllers\ScheduleController;
use Sstf\Api\Http\Controllers\SetController;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Http\Middleware\RequireJsonContentType;
use Sstf\Api\Http\Middleware\SessionAuth;
use Sstf\Api\Infrastructure\Sqlite\ExerciseRepository;
use Sstf\Api\Infrastructure\Sqlite\ScheduleRepository;
use Sstf\Api\Infrastructure\Sqlite\SetRepository;
use Sstf\Api\Services\ScheduleService;
use Sstf\Api\Services\SetService;
use Sstf\Api\Tests\HttpTestCase;

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
#[CoversClass(InvalidScheduleException::class)]
#[CoversClass(InvalidSetException::class)]
#[CoversClass(ScheduleNotFoundException::class)]
#[CoversClass(SetNotFoundException::class)]
#[CoversClass(CatalogExerciseNotFoundException::class)]
#[CoversClass(SessionAuth::class)]
#[CoversClass(RequireJsonContentType::class)]
#[CoversClass(JsonResponder::class)]
final class SchedulesTest extends HttpTestCase
{
    public function testFirstScheduleIsActiveAndSecondIsNot(): void
    {
        $this->signIn('sched-' . bin2hex(random_bytes(4)) . '@example.com');

        $first = $this->request('POST', '/api/schedules', ['name' => '  Hypertrophy  ']);
        $this->assertSame(200, $first->getStatusCode());
        $created = $this->json($first)['data'];
        $this->assertSame('Hypertrophy', $created['name']);
        $this->assertTrue($created['is_active']);
        $this->assertSame(0, $created['set_count']);
        $this->assertIsInt($created['id']);

        $second = $this->request('POST', '/api/schedules', ['name' => 'Cut']);
        $this->assertSame(200, $second->getStatusCode());
        $this->assertFalse($this->json($second)['data']['is_active']);
        $this->assertNotSame($created['id'], $this->json($second)['data']['id']);

        $listed = $this->json($this->request('GET', '/api/schedules'));
        $this->assertCount(2, $listed['data']['schedules']);
        $this->assertTrue($listed['data']['schedules'][0]['is_active']);
        $this->assertFalse($listed['data']['schedules'][1]['is_active']);
        $this->assertSame('Hypertrophy', $listed['data']['schedules'][0]['name']);
        $this->assertSame('Cut', $listed['data']['schedules'][1]['name']);
    }

    public function testActivateSwitchesTheUniqueActiveRow(): void
    {
        $this->signIn('act-' . bin2hex(random_bytes(4)) . '@example.com');
        $a = $this->json($this->request('POST', '/api/schedules', ['name' => 'A']))['data'];
        $b = $this->json($this->request('POST', '/api/schedules', ['name' => 'B']))['data'];
        $this->assertTrue($a['is_active']);
        $this->assertFalse($b['is_active']);

        $activated = $this->request('POST', '/api/schedules/' . $b['id'] . '/activate', []);
        $this->assertSame(200, $activated->getStatusCode());
        $this->assertTrue($this->json($activated)['data']['is_active']);
        $this->assertSame($b['id'], $this->json($activated)['data']['id']);

        $listed = $this->json($this->request('GET', '/api/schedules'))['data']['schedules'];
        $active = array_values(array_filter($listed, static fn (array $row): bool => $row['is_active'] === true));
        $this->assertCount(1, $active);
        $this->assertSame($b['id'], $active[0]['id']);
        $this->assertFalse($listed[0]['is_active']);
        $this->assertTrue($listed[1]['is_active']);

        $again = $this->request('POST', '/api/schedules/' . $b['id'] . '/activate', []);
        $this->assertSame(200, $again->getStatusCode());
        $this->assertTrue($this->json($again)['data']['is_active']);
        $listedAgain = $this->json($this->request('GET', '/api/schedules'))['data']['schedules'];
        $this->assertCount(1, array_values(array_filter($listedAgain, static fn (array $row): bool => $row['is_active'])));
    }

    public function testArchiveOmitsFromListAndDoesNotDropTablesOrLogs(): void
    {
        $email = 'arch-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email);
        $schedule = $this->json($this->request('POST', '/api/schedules', ['name' => 'Hypertrophy']))['data'];
        $set = $this->json($this->request('POST', '/api/schedules/' . $schedule['id'] . '/sets', [
            'name' => 'Evening',
            'day_of_week' => 3,
            'start_minutes' => 1080,
        ]))['data'];

        $pdo = new PDO('sqlite:' . $this->userDbPath($email));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $now = gmdate('c');
        $insert = $pdo->prepare(
            'INSERT INTO logs (
                logged_at, schedule_id, schedule_name, set_id, set_name, set_day_of_week, set_start_minutes,
                global_exercise_id, exercise_name, muscle_group, weight, weight_unit, reps, notes
            ) VALUES (
                :logged_at, :schedule_id, :schedule_name, :set_id, :set_name, :set_day_of_week, :set_start_minutes,
                :global_exercise_id, :exercise_name, :muscle_group, :weight, :weight_unit, :reps, :notes
            )',
        );
        $insert->execute([
            'logged_at' => $now,
            'schedule_id' => $schedule['id'],
            'schedule_name' => 'Hypertrophy',
            'set_id' => $set['id'],
            'set_name' => 'Evening',
            'set_day_of_week' => 3,
            'set_start_minutes' => 1080,
            'global_exercise_id' => 1,
            'exercise_name' => 'Bench Press',
            'muscle_group' => 'Chest',
            'weight' => 185,
            'weight_unit' => 'lb',
            'reps' => 8,
            'notes' => null,
        ]);

        $archived = $this->request('DELETE', '/api/schedules/' . $schedule['id'], []);
        $this->assertSame(200, $archived->getStatusCode());
        $this->assertTrue($this->json($archived)['data']['ok']);

        $listed = $this->json($this->request('GET', '/api/schedules'));
        $this->assertSame([], $listed['data']['schedules']);

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertContains('schedules', $tables);
        $this->assertContains('sets', $tables);
        $this->assertContains('logs', $tables);
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM logs')->fetchColumn());
        $row = $pdo->query('SELECT archived_at, is_active, name FROM schedules WHERE id = ' . (int) $schedule['id'])->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertNotNull($row['archived_at']);
        $this->assertNotSame('', $row['archived_at']);
        $this->assertSame(0, (int) $row['is_active']);
        $this->assertSame('Hypertrophy', $row['name']);
        $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM sets')->fetchColumn());

        $missing = $this->request('GET', '/api/schedules/' . $schedule['id'] . '/sets');
        $this->assertSame(404, $missing->getStatusCode());
        $this->assertSame('not_found', $this->json($missing)['error']['code']);

        $activateArchived = $this->request('POST', '/api/schedules/' . $schedule['id'] . '/activate', []);
        $this->assertSame(404, $activateArchived->getStatusCode());

        $renameArchived = $this->request('PATCH', '/api/schedules/' . $schedule['id'], ['name' => 'Nope']);
        $this->assertSame(404, $renameArchived->getStatusCode());

        $deleteAgain = $this->request('DELETE', '/api/schedules/' . $schedule['id'], []);
        $this->assertSame(404, $deleteAgain->getStatusCode());
    }

    public function testWednesdayEveningSetPersistsDenormalizedExercises(): void
    {
        $this->signIn('week-' . bin2hex(random_bytes(4)) . '@example.com');
        $schedule = $this->json($this->request('POST', '/api/schedules', ['name' => 'Hypertrophy']))['data'];
        $this->assertTrue($schedule['is_active']);

        $createdSet = $this->request('POST', '/api/schedules/' . $schedule['id'] . '/sets', [
            'name' => 'Evening',
            'day_of_week' => 3,
            'start_minutes' => 1080,
            'sort_order' => 1,
        ]);
        $this->assertSame(200, $createdSet->getStatusCode());
        $set = $this->json($createdSet)['data'];
        $this->assertSame('Evening', $set['name']);
        $this->assertSame(3, $set['day_of_week']);
        $this->assertSame(1080, $set['start_minutes']);
        $this->assertSame(1, $set['sort_order']);
        $this->assertSame($schedule['id'], $set['schedule_id']);
        $this->assertSame([], $set['exercises']);

        $catalog = $this->json($this->request('GET', '/api/exercises'))['data']['exercises'];
        $byName = [];
        foreach ($catalog as $row) {
            $byName[$row['name']] = $row;
        }
        $this->assertArrayHasKey('Bench Press', $byName);
        $this->assertArrayHasKey('Barbell Row', $byName);

        $put = $this->request('PUT', '/api/sets/' . $set['id'] . '/exercises', [
            'exercises' => [
                ['global_exercise_id' => $byName['Bench Press']['id']],
                ['global_exercise_id' => $byName['Barbell Row']['id']],
            ],
        ]);
        $this->assertSame(200, $put->getStatusCode());
        $exercises = $this->json($put)['data']['exercises'];
        $this->assertCount(2, $exercises);
        $this->assertSame('Bench Press', $exercises[0]['name']);
        $this->assertSame('Chest', $exercises[0]['muscle_group']);
        $this->assertSame('Barbell', $exercises[0]['equipment']);
        $this->assertSame($byName['Bench Press']['id'], $exercises[0]['global_exercise_id']);
        $this->assertSame(0, $exercises[0]['sort_order']);
        $this->assertSame('Barbell Row', $exercises[1]['name']);
        $this->assertSame('Back', $exercises[1]['muscle_group']);
        $this->assertSame('Barbell', $exercises[1]['equipment']);
        $this->assertSame($byName['Barbell Row']['id'], $exercises[1]['global_exercise_id']);
        $this->assertSame(1, $exercises[1]['sort_order']);

        $reloaded = $this->json($this->request('GET', '/api/schedules/' . $schedule['id'] . '/sets'));
        $this->assertCount(1, $reloaded['data']['sets']);
        $loaded = $reloaded['data']['sets'][0];
        $this->assertSame('Evening', $loaded['name']);
        $this->assertSame(3, $loaded['day_of_week']);
        $this->assertSame(1080, $loaded['start_minutes']);
        $this->assertCount(2, $loaded['exercises']);
        $this->assertSame('Bench Press', $loaded['exercises'][0]['name']);
        $this->assertSame('Barbell Row', $loaded['exercises'][1]['name']);
        $this->assertSame($byName['Bench Press']['id'], $loaded['exercises'][0]['global_exercise_id']);

        $listed = $this->json($this->request('GET', '/api/schedules'))['data']['schedules'];
        $this->assertCount(1, $listed);
        $this->assertTrue($listed[0]['is_active']);
        $this->assertSame(1, $listed[0]['set_count']);
        $this->assertSame('Hypertrophy', $listed[0]['name']);
    }

    public function testInvalidDayAndMinutesAreRejected(): void
    {
        $this->signIn('bad-' . bin2hex(random_bytes(4)) . '@example.com');
        $scheduleId = $this->json($this->request('POST', '/api/schedules', ['name' => 'Plan']))['data']['id'];
        $path = '/api/schedules/' . $scheduleId . '/sets';

        $dayHigh = $this->request('POST', $path, [
            'name' => 'Evening',
            'day_of_week' => 7,
            'start_minutes' => 1080,
        ]);
        $this->assertSame(400, $dayHigh->getStatusCode());
        $this->assertSame('invalid_request', $this->json($dayHigh)['error']['code']);

        $dayLow = $this->request('POST', $path, [
            'name' => 'Evening',
            'day_of_week' => -1,
            'start_minutes' => 0,
        ]);
        $this->assertSame(400, $dayLow->getStatusCode());

        $minHigh = $this->request('POST', $path, [
            'name' => 'Evening',
            'day_of_week' => 0,
            'start_minutes' => 1440,
        ]);
        $this->assertSame(400, $minHigh->getStatusCode());

        $minLow = $this->request('POST', $path, [
            'name' => 'Evening',
            'day_of_week' => 6,
            'start_minutes' => -1,
        ]);
        $this->assertSame(400, $minLow->getStatusCode());

        $okSunday = $this->request('POST', $path, [
            'name' => 'Morning',
            'day_of_week' => 0,
            'start_minutes' => 0,
        ]);
        $this->assertSame(200, $okSunday->getStatusCode());
        $this->assertSame(0, $this->json($okSunday)['data']['day_of_week']);
        $this->assertSame(0, $this->json($okSunday)['data']['start_minutes']);

        $okSaturday = $this->request('POST', $path, [
            'name' => 'Late',
            'day_of_week' => 6,
            'start_minutes' => 1439,
        ]);
        $this->assertSame(200, $okSaturday->getStatusCode());
        $this->assertSame(6, $this->json($okSaturday)['data']['day_of_week']);
        $this->assertSame(1439, $this->json($okSaturday)['data']['start_minutes']);
        $this->assertSame(0, $this->json($okSaturday)['data']['sort_order']);
    }

    public function testUnauthenticatedScheduleRoutesAre401(): void
    {
        $get = $this->request('GET', '/api/schedules');
        $this->assertSame(401, $get->getStatusCode());
        $this->assertSame('unauthenticated', $this->json($get)['error']['code']);

        $post = $this->request('POST', '/api/schedules', ['name' => 'Nope']);
        $this->assertSame(401, $post->getStatusCode());

        $patch = $this->request('PATCH', '/api/schedules/1', ['name' => 'Nope']);
        $this->assertSame(401, $patch->getStatusCode());

        $activate = $this->request('POST', '/api/schedules/1/activate', []);
        $this->assertSame(401, $activate->getStatusCode());

        $delete = $this->request('DELETE', '/api/schedules/1', []);
        $this->assertSame(401, $delete->getStatusCode());

        $sets = $this->request('GET', '/api/schedules/1/sets');
        $this->assertSame(401, $sets->getStatusCode());

        $createSet = $this->request('POST', '/api/schedules/1/sets', [
            'name' => 'X',
            'day_of_week' => 1,
            'start_minutes' => 60,
        ]);
        $this->assertSame(401, $createSet->getStatusCode());

        $patchSet = $this->request('PATCH', '/api/sets/1', ['name' => 'X']);
        $this->assertSame(401, $patchSet->getStatusCode());

        $deleteSet = $this->request('DELETE', '/api/sets/1', []);
        $this->assertSame(401, $deleteSet->getStatusCode());

        $put = $this->request('PUT', '/api/sets/1/exercises', ['exercises' => []]);
        $this->assertSame(401, $put->getStatusCode());
    }

    public function testRenamePatchSetDeleteAndReplaceValidation(): void
    {
        $this->signIn('more-' . bin2hex(random_bytes(4)) . '@example.com');
        $schedule = $this->json($this->request('POST', '/api/schedules', ['name' => 'Base']))['data'];

        $renamed = $this->request('PATCH', '/api/schedules/' . $schedule['id'], ['name' => '  Strength  ']);
        $this->assertSame(200, $renamed->getStatusCode());
        $this->assertSame('Strength', $this->json($renamed)['data']['name']);
        $this->assertTrue($this->json($renamed)['data']['is_active']);

        $blankName = $this->request('POST', '/api/schedules', ['name' => '   ']);
        $this->assertSame(400, $blankName->getStatusCode());

        $blankRename = $this->request('PATCH', '/api/schedules/' . $schedule['id'], ['name' => '   ']);
        $this->assertSame(400, $blankRename->getStatusCode());

        $set = $this->json($this->request('POST', '/api/schedules/' . $schedule['id'] . '/sets', [
            'name' => '  Morning  ',
            'day_of_week' => 1,
            'start_minutes' => 420,
            'sort_order' => 2,
        ]))['data'];
        $this->assertSame('Morning', $set['name']);

        $patched = $this->request('PATCH', '/api/sets/' . $set['id'], [
            'name' => 'AM',
            'day_of_week' => 2,
            'start_minutes' => 480,
            'sort_order' => 9,
        ]);
        $this->assertSame(200, $patched->getStatusCode());
        $this->assertSame('AM', $this->json($patched)['data']['name']);
        $this->assertSame(2, $this->json($patched)['data']['day_of_week']);
        $this->assertSame(480, $this->json($patched)['data']['start_minutes']);
        $this->assertSame(9, $this->json($patched)['data']['sort_order']);

        $noop = $this->request('PATCH', '/api/sets/' . $set['id'], []);
        $this->assertSame(200, $noop->getStatusCode());
        $this->assertSame('AM', $this->json($noop)['data']['name']);

        $badDay = $this->request('PATCH', '/api/sets/' . $set['id'], ['day_of_week' => 8]);
        $this->assertSame(400, $badDay->getStatusCode());

        $blankSetName = $this->request('PATCH', '/api/sets/' . $set['id'], ['name' => '   ']);
        $this->assertSame(400, $blankSetName->getStatusCode());

        $badMin = $this->request('PATCH', '/api/sets/' . $set['id'], ['start_minutes' => 2000]);
        $this->assertSame(400, $badMin->getStatusCode());

        $unknownExercise = $this->request('PUT', '/api/sets/' . $set['id'] . '/exercises', [
            'exercises' => [['global_exercise_id' => 999999]],
        ]);
        $this->assertSame(400, $unknownExercise->getStatusCode());
        $this->assertSame('invalid_request', $this->json($unknownExercise)['error']['code']);

        $cleared = $this->request('PUT', '/api/sets/' . $set['id'] . '/exercises', ['exercises' => []]);
        $this->assertSame(200, $cleared->getStatusCode());
        $this->assertSame([], $this->json($cleared)['data']['exercises']);

        $malformed = $this->request('PUT', '/api/sets/' . $set['id'] . '/exercises', ['exercises' => ['nope']]);
        $this->assertSame(400, $malformed->getStatusCode());

        $deleted = $this->request('DELETE', '/api/sets/' . $set['id'], []);
        $this->assertSame(200, $deleted->getStatusCode());
        $this->assertTrue($this->json($deleted)['data']['ok']);

        $putGone = $this->request('PUT', '/api/sets/' . $set['id'] . '/exercises', ['exercises' => []]);
        $this->assertSame(404, $putGone->getStatusCode());

        $gone = $this->request('GET', '/api/schedules/' . $schedule['id'] . '/sets');
        $this->assertSame([], $this->json($gone)['data']['sets']);

        $deleteMissing = $this->request('DELETE', '/api/sets/' . $set['id'], []);
        $this->assertSame(404, $deleteMissing->getStatusCode());

        $missingScheduleSets = $this->request('GET', '/api/schedules/9999/sets');
        $this->assertSame(404, $missingScheduleSets->getStatusCode());

        $createOnMissing = $this->request('POST', '/api/schedules/9999/sets', [
            'name' => 'X',
            'day_of_week' => 1,
            'start_minutes' => 10,
        ]);
        $this->assertSame(404, $createOnMissing->getStatusCode());

        $noJson = $this->request('POST', '/api/schedules');
        $this->assertSame(415, $noJson->getStatusCode());
        $this->assertSame('invalid_content_type', $this->json($noJson)['error']['code']);
    }

    public function testCreateAfterArchiveAutoActivatesWhenNoneAreActive(): void
    {
        $this->signIn('again-' . bin2hex(random_bytes(4)) . '@example.com');
        $first = $this->json($this->request('POST', '/api/schedules', ['name' => 'Old']))['data'];
        $this->request('DELETE', '/api/schedules/' . $first['id'], []);
        $next = $this->json($this->request('POST', '/api/schedules', ['name' => 'New']))['data'];
        $this->assertTrue($next['is_active']);
        $this->assertNotSame($first['id'], $next['id']);
    }
}
