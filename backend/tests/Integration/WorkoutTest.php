<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use Sstf\Api\Domain\ClosestSet;
use Sstf\Api\Domain\ExerciseLog;
use Sstf\Api\Domain\ExerciseNotOnSetException;
use Sstf\Api\Domain\InvalidLogException;
use Sstf\Api\Domain\LogNotFoundException;
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
use Sstf\Api\Http\Middleware\RequireJsonContentType;
use Sstf\Api\Http\Middleware\SessionAuth;
use Sstf\Api\Infrastructure\Sqlite\LogRepository;
use Sstf\Api\Infrastructure\Sqlite\ScheduleRepository;
use Sstf\Api\Infrastructure\Sqlite\SetRepository;
use Sstf\Api\Services\LogService;
use Sstf\Api\Services\WorkoutService;
use Sstf\Api\Tests\HttpTestCase;

#[CoversClass(WorkoutController::class)]
#[CoversClass(LogController::class)]
#[CoversClass(WorkoutService::class)]
#[CoversClass(LogService::class)]
#[CoversClass(LogRepository::class)]
#[CoversClass(ScheduleRepository::class)]
#[CoversClass(SetRepository::class)]
#[CoversClass(ClosestSet::class)]
#[CoversClass(WorkoutCurrent::class)]
#[CoversClass(WorkoutSetSummary::class)]
#[CoversClass(ExerciseLog::class)]
#[CoversClass(LogPrefill::class)]
#[CoversClass(InvalidLogException::class)]
#[CoversClass(LogNotFoundException::class)]
#[CoversClass(ExerciseNotOnSetException::class)]
#[CoversClass(SetNotFoundException::class)]
#[CoversClass(UnauthenticatedException::class)]
#[CoversClass(Schedule::class)]
#[CoversClass(TrainingSet::class)]
#[CoversClass(SetExercise::class)]
#[CoversClass(SessionAuth::class)]
#[CoversClass(RequireJsonContentType::class)]
#[CoversClass(JsonResponder::class)]
final class WorkoutTest extends HttpTestCase
{
    public function testWednesdayEveningIsCurrentAndPrefillsAfterLog(): void
    {
        $email = 'wo-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email, 'America/Chicago');
        $seeded = $this->seedHypertrophyWeek();
        $this->freezeAt('2026-08-19 18:40:00', 'America/Chicago');

        $current = $this->request('GET', '/api/workout/current');
        $this->assertSame(200, $current->getStatusCode());
        $data = $this->json($current)['data'];
        $this->assertSame('Hypertrophy', $data['schedule']['name']);
        $this->assertTrue($data['schedule']['is_active']);
        $this->assertSame('Evening', $data['set']['name']);
        $this->assertSame($seeded['eveningId'], $data['set']['id']);
        $this->assertTrue($data['set']['is_closest']);
        $this->assertSame($seeded['eveningId'], $data['closest_set_id']);
        $this->assertSame('lb', $data['weight_unit']);
        $this->assertNull($data['empty']);
        $this->assertCount(2, $data['exercises']);
        $this->assertSame('Bench Press', $data['exercises'][0]['name']);
        $this->assertSame('Chest', $data['exercises'][0]['muscle_group']);
        $this->assertNull($data['exercises'][0]['last_weight']);
        $this->assertNull($data['exercises'][0]['last_reps']);
        $this->assertNull($data['exercises'][0]['best_weight']);
        $this->assertNull($data['exercises'][0]['best_reps']);
        $this->assertSame('Barbell Row', $data['exercises'][1]['name']);
        $this->assertNull($data['exercises'][1]['last_weight']);

        $logged = $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 190,
            'reps' => 6,
        ]);
        $this->assertSame(200, $logged->getStatusCode());
        $row = $this->json($logged)['data'];
        $this->assertSame('Hypertrophy', $row['schedule_name']);
        $this->assertSame('Evening', $row['set_name']);
        $this->assertSame(3, $row['set_day_of_week']);
        $this->assertSame(1080, $row['set_start_minutes']);
        $this->assertSame('Bench Press', $row['exercise_name']);
        $this->assertSame('Chest', $row['muscle_group']);
        $this->assertEquals(190, $row['weight']);
        $this->assertSame('lb', $row['weight_unit']);
        $this->assertSame(6, $row['reps']);
        $this->assertNull($row['notes']);

        $after = $this->json($this->request('GET', '/api/workout/current'))['data'];
        $this->assertSame('Evening', $after['set']['name']);
        $this->assertEquals(190, $after['exercises'][0]['last_weight']);
        $this->assertSame(6, $after['exercises'][0]['last_reps']);
        $this->assertEquals(190, $after['exercises'][0]['best_weight']);
        $this->assertSame(6, $after['exercises'][0]['best_reps']);
        $this->assertNull($after['exercises'][1]['last_weight']);
    }

    public function testLogSnapshotSurvivesScheduleRename(): void
    {
        $email = 'snap-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email, 'America/Chicago');
        $seeded = $this->seedHypertrophyWeek();
        $this->freezeAt('2026-08-19 18:40:00', 'America/Chicago');

        $logged = $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 185.5,
            'reps' => 8,
            'notes' => '  deep  ',
        ]);
        $this->assertSame(200, $logged->getStatusCode());
        $logId = $this->json($logged)['data']['id'];

        $renamed = $this->request('PATCH', '/api/schedules/' . $seeded['scheduleId'], ['name' => 'Cut']);
        $this->assertSame(200, $renamed->getStatusCode());
        $this->assertSame('Cut', $this->json($renamed)['data']['name']);

        $pdo = new PDO('sqlite:' . $this->userDbPath($email));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $row = $pdo->query('SELECT schedule_name, set_name, exercise_name, weight, reps, notes FROM logs WHERE id = ' . (int) $logId)
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame('Hypertrophy', $row['schedule_name']);
        $this->assertSame('Evening', $row['set_name']);
        $this->assertSame('Bench Press', $row['exercise_name']);
        $this->assertSame(185.5, (float) $row['weight']);
        $this->assertSame(8, (int) $row['reps']);
        $this->assertSame('deep', $row['notes']);
    }

    public function testSetOverrideDoesNotDeactivateSchedule(): void
    {
        $this->signIn('ov-' . bin2hex(random_bytes(4)) . '@example.com', 'America/Chicago');
        $seeded = $this->seedHypertrophyWeek();
        $this->freezeAt('2026-08-19 18:40:00', 'America/Chicago');

        $overridden = $this->request('GET', '/api/workout/current?set_id=' . $seeded['morningId']);
        $this->assertSame(200, $overridden->getStatusCode());
        $data = $this->json($overridden)['data'];
        $this->assertSame('Morning', $data['set']['name']);
        $this->assertSame($seeded['morningId'], $data['set']['id']);
        $this->assertFalse($data['set']['is_closest']);
        $this->assertSame($seeded['eveningId'], $data['closest_set_id']);
        $this->assertTrue($data['schedule']['is_active']);

        $listed = $this->json($this->request('GET', '/api/schedules'))['data']['schedules'];
        $this->assertCount(1, $listed);
        $this->assertTrue($listed[0]['is_active']);
        $this->assertSame($seeded['scheduleId'], $listed[0]['id']);
        $this->assertSame('Hypertrophy', $listed[0]['name']);
    }

    public function testPrefillFallsBackToLastEverForExercise(): void
    {
        $this->signIn('fb-' . bin2hex(random_bytes(4)) . '@example.com', 'America/Chicago');
        $seeded = $this->seedHypertrophyWeek();
        $this->freezeAt('2026-08-20 07:10:00', 'America/Chicago');

        $onMorning = $this->request('POST', '/api/logs', [
            'set_id' => $seeded['morningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 200,
            'reps' => 5,
        ]);
        $this->assertSame(200, $onMorning->getStatusCode());

        $this->freezeAt('2026-08-19 18:40:00', 'America/Chicago');
        $evening = $this->json($this->request('GET', '/api/workout/current'))['data'];
        $this->assertSame('Evening', $evening['set']['name']);
        $this->assertEquals(200, $evening['exercises'][0]['last_weight']);
        $this->assertSame(5, $evening['exercises'][0]['last_reps']);
        $this->assertEquals(200, $evening['exercises'][0]['best_weight']);
        $this->assertSame(5, $evening['exercises'][0]['best_reps']);
    }

    public function testBestPrefersHeavierWeightThenHigherReps(): void
    {
        $this->signIn('best-' . bin2hex(random_bytes(4)) . '@example.com', 'America/Chicago');
        $seeded = $this->seedHypertrophyWeek();

        $this->freezeAt('2026-08-17 18:40:00', 'America/Chicago');
        $this->assertSame(200, $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 180,
            'reps' => 10,
        ])->getStatusCode());

        $this->freezeAt('2026-08-18 18:40:00', 'America/Chicago');
        $this->assertSame(200, $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 200,
            'reps' => 4,
        ])->getStatusCode());

        $this->freezeAt('2026-08-19 18:40:00', 'America/Chicago');
        $this->assertSame(200, $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 200,
            'reps' => 6,
        ])->getStatusCode());

        $this->freezeAt('2026-08-19 19:00:00', 'America/Chicago');
        $this->assertSame(200, $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 190,
            'reps' => 8,
        ])->getStatusCode());

        $current = $this->json($this->request('GET', '/api/workout/current?set_id=' . $seeded['eveningId']))['data'];
        $this->assertSame('Evening', $current['set']['name']);
        $this->assertEquals(190, $current['exercises'][0]['last_weight']);
        $this->assertSame(8, $current['exercises'][0]['last_reps']);
        $this->assertEquals(200, $current['exercises'][0]['best_weight']);
        $this->assertSame(6, $current['exercises'][0]['best_reps']);
    }

    public function testSwitcherMarksClosestAndEmptyStates(): void
    {
        $this->signIn('sw-' . bin2hex(random_bytes(4)) . '@example.com', 'America/Chicago');
        $empty = $this->json($this->request('GET', '/api/workout/current'))['data'];
        $this->assertNull($empty['schedule']);
        $this->assertNull($empty['set']);
        $this->assertSame('no_schedule', $empty['empty']);
        $this->assertSame([], $empty['exercises']);

        $setsEmpty = $this->json($this->request('GET', '/api/workout/sets'))['data'];
        $this->assertNull($setsEmpty['schedule']);
        $this->assertSame([], $setsEmpty['sets']);
        $this->assertNull($setsEmpty['closest_set_id']);

        $schedule = $this->json($this->request('POST', '/api/schedules', ['name' => 'Empty']))['data'];
        $noSets = $this->json($this->request('GET', '/api/workout/current'))['data'];
        $this->assertSame('Empty', $noSets['schedule']['name']);
        $this->assertNull($noSets['set']);
        $this->assertSame('no_sets', $noSets['empty']);

        $bare = $this->json($this->request('POST', '/api/schedules/' . $schedule['id'] . '/sets', [
            'name' => 'Solo',
            'day_of_week' => 1,
            'start_minutes' => 480,
        ]))['data'];
        $noEx = $this->json($this->request('GET', '/api/workout/current'))['data'];
        $this->assertSame('Solo', $noEx['set']['name']);
        $this->assertSame('no_exercises', $noEx['empty']);
        $this->assertSame([], $noEx['exercises']);

        $this->request('DELETE', '/api/sets/' . $bare['id'], []);
        $this->request('DELETE', '/api/schedules/' . $schedule['id'], []);
        $seeded = $this->seedHypertrophyWeek();
        $this->freezeAt('2026-08-19 18:40:00', 'America/Chicago');
        $listed = $this->json($this->request('GET', '/api/workout/sets'))['data'];
        $this->assertSame('Hypertrophy', $listed['schedule']['name']);
        $this->assertTrue($listed['schedule']['is_active']);
        $this->assertSame($seeded['eveningId'], $listed['closest_set_id']);
        $this->assertCount(2, $listed['sets']);
        $byName = [];
        foreach ($listed['sets'] as $set) {
            $byName[$set['name']] = $set;
        }
        $this->assertTrue($byName['Evening']['is_closest']);
        $this->assertFalse($byName['Morning']['is_closest']);
        $this->assertSame(2, $byName['Evening']['exercise_count']);
        $this->assertSame(1, $byName['Morning']['exercise_count']);
        $this->assertSame(3, $byName['Evening']['day_of_week']);
        $this->assertSame(1080, $byName['Evening']['start_minutes']);
    }

    public function testInvalidOverrideAndLogValidation(): void
    {
        $this->signIn('bad-' . bin2hex(random_bytes(4)) . '@example.com', 'America/Chicago');
        $seeded = $this->seedHypertrophyWeek();
        $other = $this->json($this->request('POST', '/api/schedules', ['name' => 'Other']))['data'];
        $otherSet = $this->json($this->request('POST', '/api/schedules/' . $other['id'] . '/sets', [
            'name' => 'Elsewhere',
            'day_of_week' => 1,
            'start_minutes' => 60,
        ]))['data'];

        $missing = $this->request('GET', '/api/workout/current?set_id=9999');
        $this->assertSame(404, $missing->getStatusCode());
        $this->assertSame('not_found', $this->json($missing)['error']['code']);

        $malformed = $this->request('GET', '/api/workout/current?set_id=abc');
        $this->assertSame(404, $malformed->getStatusCode());

        $wrongSchedule = $this->request('GET', '/api/workout/current?set_id=' . $otherSet['id']);
        $this->assertSame(404, $wrongSchedule->getStatusCode());

        $blankOverride = $this->request('GET', '/api/workout/current?set_id=');
        $this->assertSame(200, $blankOverride->getStatusCode());

        $missingSet = $this->request('POST', '/api/logs', [
            'set_id' => 9999,
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 100,
            'reps' => 1,
        ]);
        $this->assertSame(404, $missingSet->getStatusCode());

        $wrongSet = $this->request('POST', '/api/logs', [
            'set_id' => $otherSet['id'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 100,
            'reps' => 1,
        ]);
        $this->assertSame(404, $wrongSet->getStatusCode());

        $missingExercise = $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => 999999,
            'weight' => 100,
            'reps' => 1,
        ]);
        $this->assertSame(400, $missingExercise->getStatusCode());
        $this->assertSame('invalid_request', $this->json($missingExercise)['error']['code']);

        $negative = $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => -1,
            'reps' => 1,
        ]);
        $this->assertSame(400, $negative->getStatusCode());

        $negativeReps = $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 0,
            'reps' => -1,
        ]);
        $this->assertSame(400, $negativeReps->getStatusCode());

        $zeroOk = $this->request('POST', '/api/logs', [
            'set_id' => $seeded['eveningId'],
            'global_exercise_id' => $seeded['benchId'],
            'weight' => 0,
            'reps' => 0,
            'notes' => '',
        ]);
        $this->assertSame(200, $zeroOk->getStatusCode());
        $this->assertEquals(0, $this->json($zeroOk)['data']['weight']);
        $this->assertSame(0, $this->json($zeroOk)['data']['reps']);
        $this->assertNull($this->json($zeroOk)['data']['notes']);

        $noJson = $this->request('POST', '/api/logs');
        $this->assertSame(415, $noJson->getStatusCode());
    }

    public function testUnauthenticatedWorkoutAndLogsAre401(): void
    {
        $current = $this->request('GET', '/api/workout/current');
        $this->assertSame(401, $current->getStatusCode());
        $this->assertSame('unauthenticated', $this->json($current)['error']['code']);

        $sets = $this->request('GET', '/api/workout/sets');
        $this->assertSame(401, $sets->getStatusCode());

        $post = $this->request('POST', '/api/logs', [
            'set_id' => 1,
            'global_exercise_id' => 1,
            'weight' => 1,
            'reps' => 1,
        ]);
        $this->assertSame(401, $post->getStatusCode());

        $patch = $this->request('PATCH', '/api/logs/1', ['weight' => 1, 'reps' => 1]);
        $this->assertSame(401, $patch->getStatusCode());
        $delete = $this->request('DELETE', '/api/logs/1', []);
        $this->assertSame(401, $delete->getStatusCode());
    }

    /**
     * @return array{scheduleId: int, eveningId: int, morningId: int, benchId: int, rowId: int}
     */
    private function seedHypertrophyWeek(): array
    {
        $schedule = $this->json($this->request('POST', '/api/schedules', ['name' => 'Hypertrophy']))['data'];
        $this->assertTrue($schedule['is_active']);

        $evening = $this->json($this->request('POST', '/api/schedules/' . $schedule['id'] . '/sets', [
            'name' => 'Evening',
            'day_of_week' => 3,
            'start_minutes' => 1080,
        ]))['data'];
        $morning = $this->json($this->request('POST', '/api/schedules/' . $schedule['id'] . '/sets', [
            'name' => 'Morning',
            'day_of_week' => 4,
            'start_minutes' => 420,
        ]))['data'];

        $catalog = $this->json($this->request('GET', '/api/exercises'))['data']['exercises'];
        $byName = [];
        foreach ($catalog as $row) {
            $byName[$row['name']] = $row;
        }
        $this->assertArrayHasKey('Bench Press', $byName);
        $this->assertArrayHasKey('Barbell Row', $byName);

        $this->request('PUT', '/api/sets/' . $evening['id'] . '/exercises', [
            'exercises' => [
                ['global_exercise_id' => $byName['Bench Press']['id']],
                ['global_exercise_id' => $byName['Barbell Row']['id']],
            ],
        ]);
        $this->request('PUT', '/api/sets/' . $morning['id'] . '/exercises', [
            'exercises' => [
                ['global_exercise_id' => $byName['Bench Press']['id']],
            ],
        ]);

        return [
            'scheduleId' => $schedule['id'],
            'eveningId' => $evening['id'],
            'morningId' => $morning['id'],
            'benchId' => $byName['Bench Press']['id'],
            'rowId' => $byName['Barbell Row']['id'],
        ];
    }
}
