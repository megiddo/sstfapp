<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Integration;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use Sstf\Api\Domain\DuplicateExerciseNameException;
use Sstf\Api\Domain\Exercise;
use Sstf\Api\Http\Controllers\ExerciseController;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Http\Middleware\SessionAuth;
use Sstf\Api\Infrastructure\Sqlite\ExerciseRepository;
use Sstf\Api\Infrastructure\Sqlite\LogRepository;
use Sstf\Api\Services\ExerciseService;
use Sstf\Api\Services\SuggestedExerciseService;
use Sstf\Api\Tests\HttpTestCase;

#[CoversClass(ExerciseController::class)]
#[CoversClass(ExerciseService::class)]
#[CoversClass(SuggestedExerciseService::class)]
#[CoversClass(ExerciseRepository::class)]
#[CoversClass(LogRepository::class)]
#[CoversClass(Exercise::class)]
#[CoversClass(DuplicateExerciseNameException::class)]
#[CoversClass(SessionAuth::class)]
#[CoversClass(JsonResponder::class)]
final class ExercisesTest extends HttpTestCase
{
    public function testSeededCatalogIsReadableAndIncludesBenchPress(): void
    {
        $this->signIn('catalog-' . bin2hex(random_bytes(4)) . '@example.com');

        $response = $this->request('GET', '/api/exercises');
        $this->assertSame(200, $response->getStatusCode());
        $payload = $this->json($response);
        $this->assertArrayHasKey('exercises', $payload['data']);
        $exercises = $payload['data']['exercises'];
        $this->assertIsArray($exercises);
        $this->assertGreaterThan(15, count($exercises));

        $names = [];
        foreach ($exercises as $exercise) {
            $this->assertIsArray($exercise);
            $this->assertArrayHasKey('id', $exercise);
            $this->assertArrayHasKey('name', $exercise);
            $this->assertArrayHasKey('muscle_group', $exercise);
            $this->assertArrayHasKey('equipment', $exercise);
            $this->assertArrayHasKey('notes', $exercise);
            $names[] = $exercise['name'];
        }
        $this->assertContains('Bench Press', $names);
        $this->assertNotContains('bench press', $names);
    }

    public function testQueryFiltersCaseInsensitiveOnNameAndMuscleGroup(): void
    {
        $this->signIn('search-' . bin2hex(random_bytes(4)) . '@example.com');

        $bench = $this->json($this->request('GET', '/api/exercises?q=BENCH'));
        $benchNames = array_column($bench['data']['exercises'], 'name');
        $this->assertContains('Bench Press', $benchNames);
        $this->assertNotContains('Squat', $benchNames);
        $this->assertNotSame($benchNames, array_column($this->json($this->request('GET', '/api/exercises'))['data']['exercises'], 'name'));

        $chest = $this->json($this->request('GET', '/api/exercises?q=chest'));
        $chestNames = array_column($chest['data']['exercises'], 'name');
        $this->assertContains('Bench Press', $chestNames);
        $this->assertContains('Chest Press Machine', $chestNames);
        $this->assertNotContains('Plank', $chestNames);

        $none = $this->json($this->request('GET', '/api/exercises?q=zzzz-no-such-exercise'));
        $this->assertSame([], $none['data']['exercises']);

        $empty = $this->json($this->request('GET', '/api/exercises?q='));
        $this->assertGreaterThan(15, count($empty['data']['exercises']));
    }

    public function testQueryWildcardsAreEscaped(): void
    {
        $this->signIn('like-' . bin2hex(random_bytes(4)) . '@example.com');

        $percent = $this->json($this->request('GET', '/api/exercises?q=%'));
        $this->assertSame([], $percent['data']['exercises']);

        $underscore = $this->json($this->request('GET', '/api/exercises?q=_'));
        $this->assertSame([], $underscore['data']['exercises']);
    }

    public function testCreateThenDuplicateNameIsConflictIncludingNocase(): void
    {
        $this->signIn('create-' . bin2hex(random_bytes(4)) . '@example.com');
        $name = 'Landmine Press ' . bin2hex(random_bytes(3));

        $created = $this->request('POST', '/api/exercises', [
            'name' => '  ' . $name . '  ',
            'muscle_group' => 'Shoulders',
            'equipment' => 'Barbell',
            'notes' => 'User added',
        ]);
        $this->assertSame(200, $created->getStatusCode());
        $row = $this->json($created)['data'];
        $this->assertSame($name, $row['name']);
        $this->assertSame('Shoulders', $row['muscle_group']);
        $this->assertSame('Barbell', $row['equipment']);
        $this->assertSame('User added', $row['notes']);
        $this->assertIsInt($row['id']);
        $this->assertGreaterThan(0, $row['id']);

        $listed = $this->json($this->request('GET', '/api/exercises?q=' . rawurlencode($name)));
        $this->assertSame($name, $listed['data']['exercises'][0]['name']);

        $duplicate = $this->request('POST', '/api/exercises', ['name' => $name]);
        $this->assertSame(409, $duplicate->getStatusCode());
        $this->assertSame('duplicate_name', $this->json($duplicate)['error']['code']);
        $this->assertArrayNotHasKey('data', $this->json($duplicate));

        $nocase = $this->request('POST', '/api/exercises', ['name' => strtolower($name)]);
        $this->assertSame(409, $nocase->getStatusCode());
        $this->assertSame('duplicate_name', $this->json($nocase)['error']['code']);

        $bench = $this->request('POST', '/api/exercises', ['name' => 'bench press']);
        $this->assertSame(409, $bench->getStatusCode());
        $this->assertSame('duplicate_name', $this->json($bench)['error']['code']);
    }

    public function testCreateRejectsMissingAndBlankNames(): void
    {
        $this->signIn('blank-' . bin2hex(random_bytes(4)) . '@example.com');

        $missing = $this->request('POST', '/api/exercises', ['muscle_group' => 'Chest']);
        $this->assertSame(400, $missing->getStatusCode());
        $this->assertSame('invalid_request', $this->json($missing)['error']['code']);

        $blank = $this->request('POST', '/api/exercises', ['name' => '   ']);
        $this->assertSame(400, $blank->getStatusCode());
        $this->assertSame('invalid_request', $this->json($blank)['error']['code']);
    }

    public function testUnauthenticatedCatalogAccessIs401(): void
    {
        $get = $this->request('GET', '/api/exercises');
        $this->assertSame(401, $get->getStatusCode());
        $this->assertSame('unauthenticated', $this->json($get)['error']['code']);

        $post = $this->request('POST', '/api/exercises', ['name' => 'Should Not Persist']);
        $this->assertSame(401, $post->getStatusCode());
        $this->assertSame('unauthenticated', $this->json($post)['error']['code']);
    }

    public function testUserSqliteGainsTrainingTablesAfterLogin(): void
    {
        $email = 'schema-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email, 'America/Chicago');

        $pdo = new PDO('sqlite:' . $this->userDbPath($email));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $tables = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name",
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach (['schedules', 'sets', 'set_exercises', 'logs', 'account', 'identities'] as $table) {
            $this->assertContains($table, $tables);
        }

        $indexSql = $pdo->query(
            "SELECT sql FROM sqlite_master WHERE type = 'index' AND name = 'schedules_one_active'",
        )->fetchColumn();
        $this->assertIsString($indexSql);
        $this->assertStringContainsString('is_active', $indexSql);
        $this->assertStringContainsString('WHERE', $indexSql);

        $now = gmdate('c');
        $pdo->exec(
            "INSERT INTO schedules (name, is_active, created_at, updated_at)
             VALUES ('A', 1, " . $pdo->quote($now) . ', ' . $pdo->quote($now) . ')',
        );
        try {
            $pdo->exec(
                "INSERT INTO schedules (name, is_active, created_at, updated_at)
                 VALUES ('B', 1, " . $pdo->quote($now) . ', ' . $pdo->quote($now) . ')',
            );
            $this->fail('Expected partial unique index to reject a second active schedule');
        } catch (PDOException) {
        }
        $pdo->exec(
            "INSERT INTO schedules (name, is_active, created_at, updated_at)
             VALUES ('B', 0, " . $pdo->quote($now) . ', ' . $pdo->quote($now) . ')',
        );
        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM schedules')->fetchColumn());
    }

    public function testSuggestedExercisesComeFromLogs(): void
    {
        $this->signIn('suggest-' . bin2hex(random_bytes(4)) . '@example.com', 'UTC');
        $empty = $this->request('GET', '/api/exercises/suggested');
        $this->assertSame(200, $empty->getStatusCode());
        $this->assertSame([], $this->json($empty)['data']['recent']);
        $this->assertSame([], $this->json($empty)['data']['frequent']);

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
                ['global_exercise_id' => $byName['Squat']['id']],
            ],
        ]);
        $this->request('POST', '/api/logs', [
            'set_id' => $evening['id'],
            'global_exercise_id' => $byName['Squat']['id'],
            'weight' => 225,
            'reps' => 5,
        ]);
        $this->request('POST', '/api/logs', [
            'set_id' => $evening['id'],
            'global_exercise_id' => $byName['Bench Press']['id'],
            'weight' => 185,
            'reps' => 8,
        ]);
        $this->request('POST', '/api/logs', [
            'set_id' => $evening['id'],
            'global_exercise_id' => $byName['Bench Press']['id'],
            'weight' => 190,
            'reps' => 6,
        ]);

        $suggested = $this->json($this->request('GET', '/api/exercises/suggested'))['data'];
        $recentNames = array_column($suggested['recent'], 'name');
        $frequentNames = array_column($suggested['frequent'], 'name');
        $this->assertSame('Bench Press', $recentNames[0]);
        $this->assertContains('Squat', $recentNames);
        $this->assertSame('Bench Press', $frequentNames[0]);
        $this->assertSame($byName['Bench Press']['id'], $suggested['recent'][0]['id']);
        $this->assertNull($suggested['recent'][0]['equipment']);
    }

    public function testSuggestedRequiresSession(): void
    {
        $response = $this->request('GET', '/api/exercises/suggested');
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('unauthenticated', $this->json($response)['error']['code']);
    }
}
