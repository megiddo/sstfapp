<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Domain\RepoKey;
use Sstf\Api\Http\Controllers\AuthController;
use Sstf\Api\Http\Controllers\MeController;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Http\Middleware\RequireJsonContentType;
use Sstf\Api\Http\Middleware\SessionAuth;
use Sstf\Api\Http\RedirectResponder;
use Sstf\Api\Infrastructure\Google\OAuthStateService;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;
use Sstf\Api\Services\AuthService;
use Sstf\Api\Tests\Fakes\FakeGoogleOAuthClient;
use Sstf\Api\Tests\HttpTestCase;

#[CoversClass(AuthController::class)]
#[CoversClass(MeController::class)]
#[CoversClass(AuthService::class)]
#[CoversClass(UserDirectory::class)]
#[CoversClass(SessionAuth::class)]
#[CoversClass(RequireJsonContentType::class)]
#[CoversClass(JsonResponder::class)]
#[CoversClass(RedirectResponder::class)]
#[CoversClass(OAuthStateService::class)]
final class AuthGoogleTest extends HttpTestCase
{
    public function testUnverifiedEmailRedirectsAndCreatesNoUserFile(): void
    {
        $email = 'unverified-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleOAuth->willReturnUser(
            'unverified-code',
            FakeGoogleOAuthClient::user($email, false),
        );

        $before = glob($this->dataDir . '/users/*.sqlite') ?: [];
        $response = $this->completeGoogleOAuth('unverified-code', 'America/Chicago');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login?error=email_unverified', $response->getHeaderLine('Location'));
        $this->assertFileDoesNotExist($this->userDbPath($email));
        $this->assertSame($before, glob($this->dataDir . '/users/*.sqlite') ?: []);
    }

    public function testInvalidCodeAndStateAreRejected(): void
    {
        $email = 'invalid-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleOAuth->willFail('expired-code', new InvalidGoogleIdTokenException());

        $failed = $this->completeGoogleOAuth('expired-code');
        $this->assertSame(302, $failed->getStatusCode());
        $this->assertSame('/login?error=google', $failed->getHeaderLine('Location'));
        $this->assertFileDoesNotExist($this->userDbPath($email));

        $missing = $this->request('GET', '/api/auth/google/callback?code=missing-code&state=nope');
        $this->assertSame(302, $missing->getStatusCode());
        $this->assertSame('/login?error=google', $missing->getHeaderLine('Location'));
    }

    public function testTwoGoogleLoginsSameEmailCreateOneSqliteFile(): void
    {
        $email = 'twice-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleOAuth->willReturnUser('t1', FakeGoogleOAuthClient::user($email, true, 'sub-a'));
        $this->googleOAuth->willReturnUser('t2', FakeGoogleOAuthClient::user($email, true, 'sub-a'));

        $first = $this->completeGoogleOAuth('t1', 'America/Chicago');
        $this->assertSame(302, $first->getStatusCode());
        $this->assertSame('/', $first->getHeaderLine('Location'));
        $path = $this->userDbPath($email);
        $this->assertFileExists($path);

        $this->cookies = [];
        $second = $this->completeGoogleOAuth('t2', 'Europe/London');
        $this->assertSame(302, $second->getStatusCode());
        $this->assertFileExists($path);

        $matches = glob($this->dataDir . '/users/*.sqlite') ?: [];
        $this->assertCount(1, $matches);
        $this->assertSame($path, $matches[0]);

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
        $hash = RepoKey::google($email)->hash();
        $container = $this->app->getContainer();
        $this->assertNotNull($container);
        $factory = $container->get(UserDbFactory::class);
        $pdo = $factory->open($hash);
        $now = gmdate('c');
        $pdo->exec(
            "INSERT INTO account (id, email, email_normalized, password_hash, timezone, weight_unit, created_at, updated_at)
             VALUES (1, " . $pdo->quote($email) . ", " . $pdo->quote(strtolower($email)) . ", NULL, 'UTC', 'lb', " . $pdo->quote($now) . ", " . $pdo->quote($now) . ")",
        );
        $count = (int) $pdo->query("SELECT COUNT(*) FROM identities WHERE provider = 'google'")->fetchColumn();
        $this->assertSame(0, $count);

        $this->googleOAuth->willReturnUser('link-code', FakeGoogleOAuthClient::user($email, true, 'sub-link'));
        $response = $this->completeGoogleOAuth('link-code');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/', $response->getHeaderLine('Location'));

        $pdo = new PDO('sqlite:' . $this->userDbPath($email));
        $subject = $pdo->query("SELECT provider_subject FROM identities WHERE provider = 'google'")->fetchColumn();
        $this->assertSame('sub-link', $subject);
    }

    public function testLogoutClearsAccess(): void
    {
        $email = 'logout-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleOAuth->willReturnUser('out', FakeGoogleOAuthClient::user($email));
        $login = $this->completeGoogleOAuth('out');
        $this->assertSame(302, $login->getStatusCode());
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
        $this->googleOAuth->willReturnUser('tz1', FakeGoogleOAuthClient::user($email));
        $this->googleOAuth->willReturnUser('tz2', FakeGoogleOAuthClient::user($email));

        $this->completeGoogleOAuth('tz1', 'America/Chicago');
        $this->assertSame('America/Chicago', $this->json($this->request('GET', '/api/me'))['data']['timezone']);

        $this->cookies = [];
        $this->completeGoogleOAuth('tz2', 'Europe/Paris');
        $this->assertSame('America/Chicago', $this->json($this->request('GET', '/api/me'))['data']['timezone']);
    }

    public function testInvalidTimezoneFallsBackToUtc(): void
    {
        $email = 'tzbad-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleOAuth->willReturnUser('tzbad', FakeGoogleOAuthClient::user($email));
        $this->completeGoogleOAuth('tzbad', 'Not/A_Zone');
        $this->assertSame('UTC', $this->json($this->request('GET', '/api/me'))['data']['timezone']);
    }

    public function testMissingTimezoneFallsBackToUtc(): void
    {
        $email = 'tznone-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->googleOAuth->willReturnUser('tznone', FakeGoogleOAuthClient::user($email));
        $this->completeGoogleOAuth('tznone');
        $this->assertSame('UTC', $this->json($this->request('GET', '/api/me'))['data']['timezone']);
    }

    public function testGoogleStartRedirectsToGoogle(): void
    {
        $start = $this->request('GET', '/api/auth/google?timezone=America/Chicago');
        $this->assertSame(302, $start->getStatusCode());
        $this->assertStringContainsString('accounts.google.com', $start->getHeaderLine('Location'));
        $this->assertStringContainsString(OAuthStateService::COOKIE, $start->getHeaderLine('Set-Cookie'));
    }

    public function testGoogleErrorQueryRedirectsToLogin(): void
    {
        $this->request('GET', '/api/auth/google');
        $response = $this->request('GET', '/api/auth/google/callback?error=access_denied&state=x');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login?error=google', $response->getHeaderLine('Location'));
    }

    public function testUnconfiguredGoogleRedirectsToLogin(): void
    {
        $this->googleOAuth->configured = false;
        $response = $this->request('GET', '/api/auth/google');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login?error=google', $response->getHeaderLine('Location'));
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
        $this->googleOAuth->willReturnUser('n404', FakeGoogleOAuthClient::user($email));
        $this->completeGoogleOAuth('n404');

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
