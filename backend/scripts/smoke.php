<?php

declare(strict_types=1);

/**
 * End-to-end smoke: provision a user, seed Hypertrophy / Wed Evening,
 * log one row, export, and assert the SQLite file exists.
 *
 * Uses the Slim app bootstrap (no live Google token). Safe to run in the api container:
 *
 *   docker compose -f docker/compose.dev.yml exec -T api php scripts/smoke.php
 */

use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Sstf\Api\Domain\RepoKey;
use Sstf\Api\Infrastructure\Google\GoogleOAuthClientInterface;
use Sstf\Api\Tests\Fakes\FakeGoogleOAuthClient;

$backend = dirname(__DIR__);
require $backend . '/vendor/autoload.php';

$tmp = sys_get_temp_dir() . '/sstf-smoke-' . getmypid() . '-' . bin2hex(random_bytes(4));
mkdir($tmp . '/users', 0700, true);

$_ENV['DATA_PATH'] = $tmp;
$_ENV['SESSION_PATH'] = $tmp . '/sessions';
$_ENV['SESSION_SECRET'] = 'testing-session-secret-key';
$_ENV['GOOGLE_CLIENT_ID'] = 'test-google-client-id.apps.googleusercontent.com';
$_ENV['GOOGLE_CLIENT_SECRET'] = 'test-google-client-secret';
$_ENV['GOOGLE_REDIRECT_URI'] = 'http://localhost:5173/api/auth/google/callback';
$_ENV['APP_ENV'] = 'testing';
$_ENV['AUTH_RATE_LIMIT_MAX'] = '10000';
$_ENV['AUTH_RATE_LIMIT_WINDOW'] = '60';
putenv('DATA_PATH=' . $tmp);
putenv('SESSION_PATH=' . $tmp . '/sessions');
putenv('SESSION_SECRET=testing-session-secret-key');
putenv('GOOGLE_CLIENT_ID=' . $_ENV['GOOGLE_CLIENT_ID']);
putenv('GOOGLE_CLIENT_SECRET=test-google-client-secret');
putenv('GOOGLE_REDIRECT_URI=http://localhost:5173/api/auth/google/callback');
putenv('APP_ENV=testing');
putenv('AUTH_RATE_LIMIT_MAX=10000');
putenv('AUTH_RATE_LIMIT_WINDOW=60');

$cookies = [];
$oauth = new FakeGoogleOAuthClient();

try {
    /** @var App $app */
    $app = require $backend . '/config/bootstrap.php';
    $container = $app->getContainer();
    if (!$container instanceof Container) {
        fail('App container missing');
    }
    $container->set(GoogleOAuthClientInterface::class, $oauth);

    $email = 'smoke@example.com';
    $oauth->willReturnUser('smoke-code', FakeGoogleOAuthClient::user($email));

    $start = request($app, $cookies, 'GET', '/api/auth/google?timezone=' . rawurlencode('America/Chicago'));
    assertStatus($start, 302, 'google-start');
    $location = $start->getHeaderLine('Location');
    $query = parse_url($location, PHP_URL_QUERY);
    if (!is_string($query)) {
        fail('Google start missing Location query');
    }
    parse_str($query, $params);
    if (!isset($params['state']) || !is_string($params['state'])) {
        fail('Google start missing state');
    }
    $login = request(
        $app,
        $cookies,
        'GET',
        '/api/auth/google/callback?code=smoke-code&state=' . rawurlencode($params['state']),
    );
    assertStatus($login, 302, 'provision');

    $schedule = jsonData(request($app, $cookies, 'POST', '/api/schedules', ['name' => 'Hypertrophy']));
    $set = jsonData(request($app, $cookies, 'POST', '/api/schedules/' . $schedule['id'] . '/sets', [
        'name' => 'Evening',
        'day_of_week' => 3,
        'start_minutes' => 1080,
    ]));
    $catalog = jsonData(request($app, $cookies, 'GET', '/api/exercises'))['exercises'];
    $byName = [];
    foreach ($catalog as $row) {
        $byName[$row['name']] = $row;
    }
    if (!isset($byName['Bench Press'], $byName['Barbell Row'])) {
        fail('Seeded catalog missing Bench Press / Barbell Row');
    }
    $replaced = request($app, $cookies, 'PUT', '/api/sets/' . $set['id'] . '/exercises', [
        'exercises' => [
            ['global_exercise_id' => $byName['Bench Press']['id']],
            ['global_exercise_id' => $byName['Barbell Row']['id']],
        ],
    ]);
    assertStatus($replaced, 200, 'seed exercises');

    $logged = request($app, $cookies, 'POST', '/api/logs', [
        'set_id' => $set['id'],
        'global_exercise_id' => $byName['Bench Press']['id'],
        'weight' => 185.0,
        'reps' => 8,
    ]);
    assertStatus($logged, 200, 'log');

    $export = request($app, $cookies, 'GET', '/api/export');
    assertStatus($export, 200, 'export');
    $bytes = (string) $export->getBody();
    if (!str_starts_with($bytes, 'SQLite format 3')) {
        fail('Export is not a SQLite file');
    }

    $userFile = $tmp . '/users/' . RepoKey::google($email)->hash() . '.sqlite';
    if (!is_file($userFile)) {
        fail('User sqlite missing at ' . $userFile);
    }
    $copy = $tmp . '/export.sqlite';
    file_put_contents($copy, $bytes);
    if (!is_file($copy) || filesize($copy) < 100) {
        fail('Exported sqlite was not written');
    }

    fwrite(STDOUT, "SMOKE OK\n");
    exit(0);
} catch (Throwable $e) {
    fail($e->getMessage());
} finally {
    deleteTree($tmp);
}

/**
 * @param array<string, string> $cookies
 * @param array<string, mixed>|null $json
 */
function request(App $app, array &$cookies, string $method, string $uri, ?array $json = null): ResponseInterface
{
    $request = (new ServerRequestFactory())->createServerRequest($method, $uri, [
        'REMOTE_ADDR' => '127.0.0.1',
    ]);
    $queryString = parse_url($uri, PHP_URL_QUERY);
    if (is_string($queryString) && $queryString !== '') {
        parse_str($queryString, $query);
        $request = $request->withQueryParams($query);
    }
    $request = $request->withCookieParams($cookies);
    if ($cookies !== []) {
        $parts = [];
        foreach ($cookies as $name => $value) {
            $parts[] = $name . '=' . $value;
        }
        $request = $request->withHeader('Cookie', implode('; ', $parts));
    }
    if ($json !== null) {
        $body = (new StreamFactory())->createStream((string) json_encode($json, JSON_THROW_ON_ERROR));
        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body)
            ->withParsedBody($json);
    }
    $response = $app->handle($request);
    foreach ($response->getHeader('Set-Cookie') as $line) {
        $pair = explode(';', $line, 2)[0];
        $eq = strpos($pair, '=');
        if ($eq === false) {
            continue;
        }
        $name = substr($pair, 0, $eq);
        $value = substr($pair, $eq + 1);
        $expired = $value === '' || preg_match('/Max-Age=0(?:;|$)/i', $line) === 1;
        if ($expired) {
            unset($cookies[$name]);
        } else {
            $cookies[$name] = $value;
        }
    }

    return $response;
}

function assertStatus(ResponseInterface $response, int $expected, string $step): void
{
    if ($response->getStatusCode() !== $expected) {
        fail($step . ' expected ' . $expected . ', got ' . $response->getStatusCode() . ' ' . (string) $response->getBody());
    }
}

/**
 * @return array<string, mixed>
 */
function jsonData(ResponseInterface $response): array
{
    $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || !isset($payload['data']) || !is_array($payload['data'])) {
        fail('Missing data envelope: ' . (string) $response->getBody());
    }

    return $payload['data'];
}

function fail(string $message): never
{
    fwrite(STDERR, 'SMOKE FAIL: ' . $message . "\n");
    exit(1);
}

function deleteTree(string $path): void
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
            deleteTree($full);
        } else {
            unlink($full);
        }
    }
    rmdir($path);
}
