<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Http\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Sstf\Api\Domain\AccountExistsException;
use Sstf\Api\Domain\RepoKey;
use Sstf\Api\Domain\SystemClock;
use Sstf\Api\Http\Controllers\AuthController;
use Sstf\Api\Http\Controllers\MeController;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Infrastructure\Session\FileSessionStore;
use Sstf\Api\Infrastructure\Session\SessionCookie;
use Sstf\Api\Infrastructure\Session\SessionService;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;
use Sstf\Api\Services\AuthService;
use Sstf\Api\Tests\Fakes\FakeGoogleIdTokenVerifier;

#[CoversClass(AuthController::class)]
#[CoversClass(MeController::class)]
#[CoversClass(JsonResponder::class)]
#[CoversClass(AccountExistsException::class)]
final class AuthControllerTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-ctl-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/users', 0700, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testGoogleMapsDomainErrorsAndSetsCookie(): void
    {
        $verifier = new FakeGoogleIdTokenVerifier();
        $sessions = $this->sessions();
        $controller = new AuthController($this->auth($verifier), $sessions);
        $factory = new ServerRequestFactory();

        $unverified = $verifier;
        $unverified->willVerify('u', FakeGoogleIdTokenVerifier::user('a@b.com', false));
        $response = $controller->google(
            $factory->createServerRequest('POST', '/api/auth/google')->withParsedBody(['id_token' => 'u']),
            new Response(),
        );
        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('email_unverified', (string) $response->getBody());

        $response = $controller->google(
            $factory->createServerRequest('POST', '/api/auth/google')->withParsedBody(['id_token' => 'nope']),
            new Response(),
        );
        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('invalid_token', (string) $response->getBody());

        $response = $controller->google(
            $factory->createServerRequest('POST', '/api/auth/google')->withParsedBody(null),
            new Response(),
        );
        $this->assertSame(400, $response->getStatusCode());

        $response = $controller->google(
            $factory->createServerRequest('POST', '/api/auth/google')->withParsedBody(['id_token' => 1]),
            new Response(),
        );
        $this->assertSame(400, $response->getStatusCode());

        $verifier->willVerify('ok', FakeGoogleIdTokenVerifier::user('ok@example.com'));
        $ok = $controller->google(
            $factory->createServerRequest('POST', '/api/auth/google')->withParsedBody([
                'id_token' => 'ok',
                'timezone' => 123,
            ]),
            new Response(),
        );
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertNotSame('', $ok->getHeaderLine('Set-Cookie'));
        $this->assertStringContainsString('HttpOnly', $ok->getHeaderLine('Set-Cookie'));
    }

    public function testLogoutAndMe(): void
    {
        $verifier = new FakeGoogleIdTokenVerifier();
        $auth = $this->auth($verifier);
        $sessions = $this->sessions();
        $authController = new AuthController($auth, $sessions);
        $meController = new MeController($auth);

        $missing = $meController->me(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/me'),
            new Response(),
        );
        $this->assertSame(401, $missing->getStatusCode());

        $verifier->willVerify('ok', FakeGoogleIdTokenVerifier::user('me@example.com'));
        $login = $authController->google(
            (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/google')
                ->withParsedBody(['id_token' => 'ok']),
            new Response(),
        );
        $cookie = $this->cookieValue($login->getHeaderLine('Set-Cookie'));
        $this->assertNotSame('', $cookie);

        $hash = RepoKey::google('me@example.com')->hash();
        $me = $meController->me(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/me')
                ->withAttribute('email_hash', $hash),
            new Response(),
        );
        $this->assertSame(200, $me->getStatusCode());
        $this->assertStringContainsString('me@example.com', (string) $me->getBody());

        $gone = $meController->me(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/me')
                ->withAttribute('email_hash', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
            new Response(),
        );
        $this->assertSame(401, $gone->getStatusCode());

        $missingPatchHash = $meController->patch(
            (new ServerRequestFactory())->createServerRequest('PATCH', '/api/me'),
            new Response(),
        );
        $this->assertSame(401, $missingPatchHash->getStatusCode());

        $emptyHash = $meController->patch(
            (new ServerRequestFactory())->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', ''),
            new Response(),
        );
        $this->assertSame(401, $emptyHash->getStatusCode());

        $notArray = $meController->patch(
            (new ServerRequestFactory())->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', $hash)
                ->withParsedBody(null),
            new Response(),
        );
        $this->assertSame(400, $notArray->getStatusCode());
        $this->assertStringContainsString('invalid_request', (string) $notArray->getBody());

        $badTzType = $meController->patch(
            (new ServerRequestFactory())->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', $hash)
                ->withParsedBody(['timezone' => 1]),
            new Response(),
        );
        $this->assertSame(400, $badTzType->getStatusCode());
        $this->assertStringContainsString('invalid_timezone', (string) $badTzType->getBody());

        $badUnitType = $meController->patch(
            (new ServerRequestFactory())->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', $hash)
                ->withParsedBody(['weight_unit' => 1]),
            new Response(),
        );
        $this->assertSame(400, $badUnitType->getStatusCode());
        $this->assertStringContainsString('invalid_weight_unit', (string) $badUnitType->getBody());

        $patched = $meController->patch(
            (new ServerRequestFactory())->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', $hash)
                ->withParsedBody(['timezone' => 'Europe/Berlin', 'weight_unit' => 'kg']),
            new Response(),
        );
        $this->assertSame(200, $patched->getStatusCode());
        $patchedJson = json_decode((string) $patched->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Europe/Berlin', $patchedJson['data']['timezone']);
        $this->assertSame('kg', $patchedJson['data']['weight_unit']);

        $invalidTz = $meController->patch(
            (new ServerRequestFactory())->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', $hash)
                ->withParsedBody(['timezone' => 'Nope/Nope']),
            new Response(),
        );
        $this->assertSame(400, $invalidTz->getStatusCode());

        $invalidUnit = $meController->patch(
            (new ServerRequestFactory())->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', $hash)
                ->withParsedBody(['weight_unit' => 'st']),
            new Response(),
        );
        $this->assertSame(400, $invalidUnit->getStatusCode());

        $missingAccount = $meController->patch(
            (new ServerRequestFactory())->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')
                ->withParsedBody(['timezone' => 'UTC']),
            new Response(),
        );
        $this->assertSame(401, $missingAccount->getStatusCode());

        $logout = $authController->logout(
            (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/logout')
                ->withCookieParams(['sstf_session' => $cookie]),
            new Response(),
        );
        $this->assertSame(200, $logout->getStatusCode());
        $this->assertStringContainsString('Max-Age=0', $logout->getHeaderLine('Set-Cookie'));
    }

    public function testPasswordLoginMapsErrorsAndSetsCookie(): void
    {
        $verifier = new FakeGoogleIdTokenVerifier();
        $auth = $this->auth($verifier);
        $controller = new AuthController($auth, $this->sessions());
        $factory = new ServerRequestFactory();

        $notArray = $controller->password(
            $factory->createServerRequest('POST', '/api/auth/password')->withParsedBody(null),
            new Response(),
        );
        $this->assertSame(400, $notArray->getStatusCode());
        $this->assertStringContainsString('invalid_request', (string) $notArray->getBody());
        $this->assertStringContainsString('Sign-in failed', (string) $notArray->getBody());

        $missing = $controller->password(
            $factory->createServerRequest('POST', '/api/auth/password')->withParsedBody(['email' => 1, 'password' => 'x']),
            new Response(),
        );
        $this->assertSame(400, $missing->getStatusCode());

        $emptyPassword = $controller->password(
            $factory->createServerRequest('POST', '/api/auth/password')->withParsedBody([
                'email' => 'a@b.com',
                'password' => '',
            ]),
            new Response(),
        );
        $this->assertSame(400, $emptyPassword->getStatusCode());

        $unknown = $controller->password(
            $factory->createServerRequest('POST', '/api/auth/password')->withParsedBody([
                'email' => 'missing@example.com',
                'password' => 'secret',
            ]),
            new Response(),
        );
        $this->assertSame(401, $unknown->getStatusCode());
        $this->assertStringContainsString('invalid_credentials', (string) $unknown->getBody());
        $this->assertStringNotContainsString('secret', (string) $unknown->getBody());

        $auth->registerWithPassword('pw@example.com', 'right-pass', 'UTC');
        $ok = $controller->password(
            $factory->createServerRequest('POST', '/api/auth/password')->withParsedBody([
                'username' => 'pw@example.com',
                'password' => 'right-pass',
            ]),
            new Response(),
        );
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertNotSame('', $ok->getHeaderLine('Set-Cookie'));
        $json = json_decode((string) $ok->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('password_hash', $json['data']);
        $this->assertSame('pw@example.com', $json['data']['email']);
    }

    public function testRegisterMapsErrorsAndSetsCookie(): void
    {
        $controller = new AuthController($this->auth(new FakeGoogleIdTokenVerifier()), $this->sessions());
        $factory = new ServerRequestFactory();

        $notArray = $controller->register(
            $factory->createServerRequest('POST', '/api/auth/register')->withParsedBody(null),
            new Response(),
        );
        $this->assertSame(400, $notArray->getStatusCode());
        $this->assertStringContainsString('invalid_request', (string) $notArray->getBody());
        $this->assertStringContainsString('Registration failed', (string) $notArray->getBody());

        $missing = $controller->register(
            $factory->createServerRequest('POST', '/api/auth/register')->withParsedBody(['email' => 1, 'password' => 'x']),
            new Response(),
        );
        $this->assertSame(400, $missing->getStatusCode());
        $this->assertStringContainsString('Registration failed', (string) $missing->getBody());

        $emptyPassword = $controller->register(
            $factory->createServerRequest('POST', '/api/auth/register')->withParsedBody([
                'email' => 'a@b.com',
                'password' => '',
            ]),
            new Response(),
        );
        $this->assertSame(400, $emptyPassword->getStatusCode());
        $this->assertStringContainsString('invalid_password', (string) $emptyPassword->getBody());
        $this->assertStringContainsString('Enter a password', (string) $emptyPassword->getBody());

        $invalidEmail = $controller->register(
            $factory->createServerRequest('POST', '/api/auth/register')->withParsedBody([
                'email' => 'nope!',
                'password' => 'secret',
            ]),
            new Response(),
        );
        $this->assertSame(400, $invalidEmail->getStatusCode());
        $this->assertStringContainsString('invalid_request', (string) $invalidEmail->getBody());
        $this->assertFileDoesNotExist($this->tmp . '/users/' . md5('password|nope!') . '.sqlite');

        $ok = $controller->register(
            $factory->createServerRequest('POST', '/api/auth/register')->withParsedBody([
                'email' => 'new@example.com',
                'password' => 'starter-pass',
                'timezone' => 12,
            ]),
            new Response(),
        );
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertNotSame('', $ok->getHeaderLine('Set-Cookie'));
        $json = json_decode((string) $ok->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('new@example.com', $json['data']['email']);
        $this->assertArrayNotHasKey('password_hash', $json['data']);
        $this->assertStringNotContainsString('starter-pass', (string) $ok->getBody());
        $providers = [];
        foreach ($json['data']['identities'] as $identity) {
            $providers[] = $identity['provider'];
        }
        $this->assertSame(['password'], $providers);

        $dup = $controller->register(
            $factory->createServerRequest('POST', '/api/auth/register')->withParsedBody([
                'email' => 'new@example.com',
                'password' => 'other-pass',
            ]),
            new Response(),
        );
        $this->assertSame(409, $dup->getStatusCode());
        $this->assertStringContainsString('account_exists', (string) $dup->getBody());
        $this->assertStringContainsString('Account already exists', (string) $dup->getBody());
        $this->assertStringNotContainsString('other-pass', (string) $dup->getBody());
    }

    public function testPatchPasswordTypeErrors(): void
    {
        $verifier = new FakeGoogleIdTokenVerifier();
        $verifier->willVerify('ok', FakeGoogleIdTokenVerifier::user('me@example.com'));
        $auth = $this->auth($verifier);
        $auth->signInWithGoogle('ok', 'UTC');
        $me = new MeController($auth);
        $hash = RepoKey::google('me@example.com')->hash();
        $factory = new ServerRequestFactory();

        $badPassword = $me->patch(
            $factory->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', $hash)
                ->withParsedBody(['password' => 1]),
            new Response(),
        );
        $this->assertSame(400, $badPassword->getStatusCode());
        $this->assertStringContainsString('invalid_password', (string) $badPassword->getBody());

        $badCurrent = $me->patch(
            $factory->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', $hash)
                ->withParsedBody(['password' => 'x', 'current_password' => 1]),
            new Response(),
        );
        $this->assertSame(400, $badCurrent->getStatusCode());
        $this->assertStringContainsString('invalid_current_password', (string) $badCurrent->getBody());

        $empty = $me->patch(
            $factory->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', $hash)
                ->withParsedBody(['password' => '']),
            new Response(),
        );
        $this->assertSame(400, $empty->getStatusCode());
        $this->assertStringContainsString('invalid_password', (string) $empty->getBody());

        $set = $me->patch(
            $factory->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', $hash)
                ->withParsedBody(['password' => 'first-pass']),
            new Response(),
        );
        $this->assertSame(200, $set->getStatusCode());
        $setJson = json_decode((string) $set->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('password_hash', $setJson['data']);
        $this->assertStringNotContainsString('first-pass', (string) $set->getBody());

        $wrongCurrent = $me->patch(
            $factory->createServerRequest('PATCH', '/api/me')
                ->withAttribute('email_hash', $hash)
                ->withParsedBody(['password' => 'second-pass', 'current_password' => 'nope']),
            new Response(),
        );
        $this->assertSame(400, $wrongCurrent->getStatusCode());
        $this->assertStringContainsString('invalid_current_password', (string) $wrongCurrent->getBody());
    }

    private function auth(FakeGoogleIdTokenVerifier $verifier): AuthService
    {
        $migrator = new Migrator();
        $root = dirname(__DIR__, 4);
        $users = new UserDbFactory($this->tmp . '/users', $migrator, $root . '/migrations/user');
        $global = new GlobalDb($this->tmp . '/global.sqlite', $migrator, $root . '/migrations/global');

        return new AuthService(
            $verifier,
            new UserDirectory($users, $global, new SystemClock()),
            $this->sessions(),
            [
                'memory_cost' => 16,
                'time_cost' => 1,
                'threads' => 1,
            ],
        );
    }

    private function sessions(): SessionService
    {
        return new SessionService(
            new FileSessionStore($this->tmp . '/sessions'),
            new SessionCookie('sstf_session', false),
            'testing-session-secret-key',
        );
    }

    private function cookieValue(string $header): string
    {
        $pair = explode(';', $header, 2)[0];
        $eq = strpos($pair, '=');
        $this->assertNotFalse($eq);

        return substr($pair, $eq + 1);
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
