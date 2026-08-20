<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use Sstf\Api\Domain\EmailKey;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Http\Controllers\AuthController;
use Sstf\Api\Http\Controllers\MeController;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Http\Middleware\RequireJsonContentType;
use Sstf\Api\Http\Middleware\SessionAuth;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;
use Sstf\Api\Services\AuthService;
use Sstf\Api\Tests\Fakes\FakeGoogleIdTokenVerifier;
use Sstf\Api\Tests\HttpTestCase;

#[CoversClass(AuthController::class)]
#[CoversClass(MeController::class)]
#[CoversClass(AuthService::class)]
#[CoversClass(UserDirectory::class)]
#[CoversClass(SessionAuth::class)]
#[CoversClass(RequireJsonContentType::class)]
#[CoversClass(JsonResponder::class)]
final class AuthGoogleTest extends HttpTestCase
{
    public function testUnverifiedEmailIsRejectedAndCreatesNoUserFile(): void
    {
        $email = 'unverified-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleVerifier->willVerify(
            'unverified-token',
            FakeGoogleIdTokenVerifier::user($email, false),
        );

        $before = glob($this->dataDir . '/users/*.sqlite') ?: [];
        $response = $this->request('POST', '/api/auth/google', [
            'id_token' => 'unverified-token',
            'timezone' => 'America/Chicago',
        ]);

        $this->assertSame(401, $response->getStatusCode());
        $payload = $this->json($response);
        $this->assertSame('email_unverified', $payload['error']['code']);
        $this->assertSame('Email not verified', $payload['error']['message']);
        $this->assertArrayNotHasKey('data', $payload);
        $this->assertFileDoesNotExist($this->userDbPath($email));
        $this->assertSame($before, glob($this->dataDir . '/users/*.sqlite') ?: []);
    }

    public function testInvalidExpiredAndWrongAudTokensAreRejected(): void
    {
        $email = 'invalid-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleVerifier->willFail('expired-token', new InvalidGoogleIdTokenException());
        $this->googleVerifier->willFail('wrong-aud-token', new InvalidGoogleIdTokenException());

        foreach (['missing-token', 'expired-token', 'wrong-aud-token'] as $token) {
            $response = $this->request('POST', '/api/auth/google', ['id_token' => $token]);
            $this->assertSame(401, $response->getStatusCode());
            $payload = $this->json($response);
            $this->assertSame('invalid_token', $payload['error']['code']);
            $this->assertSame('Google sign-in failed', $payload['error']['message']);
            $this->assertFileDoesNotExist($this->userDbPath($email));
        }
    }

    public function testTwoGoogleLoginsSameEmailCreateOneSqliteFile(): void
    {
        $email = 'twice-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleVerifier->willVerify('t1', FakeGoogleIdTokenVerifier::user($email, true, 'sub-a'));
        $this->googleVerifier->willVerify('t2', FakeGoogleIdTokenVerifier::user($email, true, 'sub-a'));

        $first = $this->request('POST', '/api/auth/google', [
            'id_token' => 't1',
            'timezone' => 'America/Chicago',
        ]);
        $this->assertSame(200, $first->getStatusCode());
        $path = $this->userDbPath($email);
        $this->assertFileExists($path);

        $second = $this->request('POST', '/api/auth/google', [
            'id_token' => 't2',
            'timezone' => 'Europe/London',
        ]);
        $this->assertSame(200, $second->getStatusCode());
        $this->assertFileExists($path);

        $matches = glob($this->dataDir . '/users/' . md5(strtolower($email)) . '.sqlite') ?: [];
        $this->assertCount(1, $matches);

        $me = $this->request('GET', '/api/me');
        $this->assertSame(200, $me->getStatusCode());
        $payload = $this->json($me);
        $this->assertSame($email, $payload['data']['email']);
        $this->assertSame('America/Chicago', $payload['data']['timezone']);
        $this->assertSame('lb', $payload['data']['weight_unit']);
        $this->assertSame([['provider' => 'google']], $payload['data']['identities']);
        $this->assertArrayNotHasKey('password_hash', $payload['data']);
        $this->assertArrayNotHasKey('provider_subject', $payload['data']['identities'][0]);
    }

    public function testExistingFileMissingGoogleIdentityIsLinked(): void
    {
        $email = 'link-' . bin2hex(random_bytes(4)) . '@example.com';
        $key = EmailKey::fromEmail($email);
        $container = $this->app->getContainer();
        $this->assertNotNull($container);
        $factory = $container->get(UserDbFactory::class);
        $pdo = $factory->open($key->hash());
        $now = gmdate('c');
        $pdo->exec(
            "INSERT INTO account (id, email, email_normalized, password_hash, timezone, weight_unit, created_at, updated_at)
             VALUES (1, " . $pdo->quote($email) . ", " . $pdo->quote($key->normalized()) . ", NULL, 'UTC', 'lb', " . $pdo->quote($now) . ", " . $pdo->quote($now) . ")",
        );
        $count = (int) $pdo->query("SELECT COUNT(*) FROM identities WHERE provider = 'google'")->fetchColumn();
        $this->assertSame(0, $count);

        $this->googleVerifier->willVerify('link-token', FakeGoogleIdTokenVerifier::user($email, true, 'sub-link'));
        $response = $this->request('POST', '/api/auth/google', ['id_token' => 'link-token']);
        $this->assertSame(200, $response->getStatusCode());

        $matches = glob($this->dataDir . '/users/' . $key->hash() . '.sqlite') ?: [];
        $this->assertCount(1, $matches);

        $pdo = new PDO('sqlite:' . $this->userDbPath($email));
        $subject = $pdo->query("SELECT provider_subject FROM identities WHERE provider = 'google'")->fetchColumn();
        $this->assertSame('sub-link', $subject);
    }

    public function testMeWithoutSessionIs401(): void
    {
        $response = $this->request('GET', '/api/me');
        $this->assertSame(401, $response->getStatusCode());
        $payload = $this->json($response);
        $this->assertSame('unauthenticated', $payload['error']['code']);
        $this->assertArrayNotHasKey('data', $payload);
    }

    public function testLogoutClearsAccess(): void
    {
        $email = 'logout-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleVerifier->willVerify('out', FakeGoogleIdTokenVerifier::user($email));

        $login = $this->request('POST', '/api/auth/google', ['id_token' => 'out']);
        $this->assertSame(200, $login->getStatusCode());
        $setCookie = $login->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('HttpOnly', $setCookie);
        $this->assertStringContainsString('SameSite=Lax', $setCookie);
        $this->assertStringNotContainsString('Secure', $setCookie);
        $this->assertStringNotContainsString($email, $setCookie);

        $me = $this->request('GET', '/api/me');
        $this->assertSame(200, $me->getStatusCode());

        $logout = $this->request('POST', '/api/auth/logout', []);
        $this->assertSame(200, $logout->getStatusCode());
        $this->assertSame(['ok' => true], $this->json($logout)['data']);
        $this->assertStringContainsString('Max-Age=0', $logout->getHeaderLine('Set-Cookie'));

        $after = $this->request('GET', '/api/me');
        $this->assertSame(401, $after->getStatusCode());
        $this->assertSame('unauthenticated', $this->json($after)['error']['code']);
    }

    public function testTimezoneCapturedOnFirstLoginOnly(): void
    {
        $email = 'tz-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleVerifier->willVerify('tz1', FakeGoogleIdTokenVerifier::user($email));
        $this->googleVerifier->willVerify('tz2', FakeGoogleIdTokenVerifier::user($email));

        $first = $this->request('POST', '/api/auth/google', [
            'id_token' => 'tz1',
            'timezone' => 'America/Chicago',
        ]);
        $this->assertSame('America/Chicago', $this->json($first)['data']['timezone']);

        $this->cookies = [];
        $second = $this->request('POST', '/api/auth/google', [
            'id_token' => 'tz2',
            'timezone' => 'Europe/Paris',
        ]);
        $this->assertSame('America/Chicago', $this->json($second)['data']['timezone']);
    }

    public function testInvalidTimezoneFallsBackToUtc(): void
    {
        $email = 'tzbad-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleVerifier->willVerify('tzbad', FakeGoogleIdTokenVerifier::user($email));

        $response = $this->request('POST', '/api/auth/google', [
            'id_token' => 'tzbad',
            'timezone' => 'Not/A_Zone',
        ]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('UTC', $this->json($response)['data']['timezone']);
    }

    public function testMissingTimezoneFallsBackToUtc(): void
    {
        $email = 'tznone-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleVerifier->willVerify('tznone', FakeGoogleIdTokenVerifier::user($email));

        $response = $this->request('POST', '/api/auth/google', ['id_token' => 'tznone']);
        $this->assertSame('UTC', $this->json($response)['data']['timezone']);
    }

    public function testUserIndexUpsertsHashOnly(): void
    {
        $email = 'index-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleVerifier->willVerify('idx', FakeGoogleIdTokenVerifier::user($email));
        $this->request('POST', '/api/auth/google', ['id_token' => 'idx']);

        $pdo = new PDO('sqlite:' . $this->dataDir . '/global.sqlite');
        $columns = $pdo->query('PRAGMA table_info(user_index)')->fetchAll(PDO::FETCH_ASSOC);
        $names = [];
        foreach ($columns as $column) {
            $names[] = $column['name'];
        }
        $this->assertContains('email_hash', $names);
        $this->assertContains('created_at', $names);
        $this->assertNotContains('email', $names);

        $rows = $pdo->query('SELECT email_hash, created_at FROM user_index')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $rows);
        $hash = md5(strtolower($email));
        $this->assertSame($hash, $rows[0]['email_hash']);
        $this->assertSame(32, strlen($rows[0]['email_hash']));
        $this->assertStringNotContainsString('@', $rows[0]['email_hash']);
        $this->assertStringNotContainsString($email, $rows[0]['email_hash']);
        $this->assertNotSame('', $rows[0]['created_at']);
    }

    public function testGoogleLoginRequiresJsonContentType(): void
    {
        $response = $this->request('POST', '/api/auth/google');
        $this->assertSame(415, $response->getStatusCode());
        $this->assertSame('invalid_content_type', $this->json($response)['error']['code']);
    }

    public function testEmptyIdTokenIsInvalidRequest(): void
    {
        $response = $this->request('POST', '/api/auth/google', ['id_token' => '  ']);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('invalid_request', $this->json($response)['error']['code']);
        $this->assertSame('Google sign-in failed', $this->json($response)['error']['message']);
    }

    public function testUnknownApiRouteWithoutSessionIs401(): void
    {
        $response = $this->request('GET', '/api/does-not-exist');
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('unauthenticated', $this->json($response)['error']['code']);
    }

    public function testUnknownApiRouteWithSessionIs404(): void
    {
        $email = '404-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleVerifier->willVerify('n404', FakeGoogleIdTokenVerifier::user($email));
        $this->request('POST', '/api/auth/google', ['id_token' => 'n404']);

        $response = $this->request('GET', '/api/does-not-exist');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('http_error', $this->json($response)['error']['code']);
    }

    public function testHealthRemainsPublic(): void
    {
        $response = $this->request('GET', '/api/health');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['ok' => true], $this->json($response)['data']);
    }
}
