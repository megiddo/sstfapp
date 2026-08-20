<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Http\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
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

        $hash = md5('me@example.com');
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

        $logout = $authController->logout(
            (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/logout')
                ->withCookieParams(['sstf_session' => $cookie]),
            new Response(),
        );
        $this->assertSame(200, $logout->getStatusCode());
        $this->assertStringContainsString('Max-Age=0', $logout->getHeaderLine('Set-Cookie'));
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
