<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Http\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Http\Middleware\AuthRateLimit;
use Sstf\Api\Http\Middleware\RequireJsonContentType;
use Sstf\Api\Http\Middleware\SessionAuth;
use Sstf\Api\Infrastructure\RateLimit\MemoryAuthRateLimiter;
use Sstf\Api\Infrastructure\Session\FileSessionStore;
use Sstf\Api\Infrastructure\Session\SessionCookie;
use Sstf\Api\Infrastructure\Session\SessionService;
use Sstf\Api\Tests\Fakes\FakeClock;
use Sstf\Api\Tests\Fakes\ThrowingAuthRateLimiter;

#[CoversClass(RequireJsonContentType::class)]
#[CoversClass(SessionAuth::class)]
#[CoversClass(AuthRateLimit::class)]
#[CoversClass(JsonResponder::class)]
final class AuthMiddlewareTest extends TestCase
{
    public function testRequireJsonAcceptsJsonAndCharset(): void
    {
        $mw = new RequireJsonContentType();
        $handler = $this->okHandler();

        $plain = $mw->process($this->request('POST', '/api/auth/password'), $handler);
        $this->assertSame(415, $plain->getStatusCode());
        $body = json_decode((string) $plain->getBody(), true);
        $this->assertSame('invalid_content_type', $body['error']['code']);

        $ok = $mw->process(
            $this->request('POST', '/api/auth/password')->withHeader('Content-Type', 'application/json'),
            $handler,
        );
        $this->assertSame(204, $ok->getStatusCode());

        $charset = $mw->process(
            $this->request('POST', '/api/auth/logout')->withHeader('Content-Type', 'application/json; charset=utf-8'),
            $handler,
        );
        $this->assertSame(204, $charset->getStatusCode());

        $wrong = $mw->process(
            $this->request('POST', '/api/auth/password')->withHeader('Content-Type', 'application/jsonx'),
            $handler,
        );
        $this->assertSame(415, $wrong->getStatusCode());
    }

    public function testSessionAuthSkipsPublicPathsAndRequiresCookie(): void
    {
        $tmp = sys_get_temp_dir() . '/sstf-mw-' . bin2hex(random_bytes(4));
        $sessions = new SessionService(
            new FileSessionStore($tmp),
            new SessionCookie('sstf_session', false),
            'testing-session-secret-key',
        );
        $mw = new SessionAuth($sessions);
        $handler = $this->okHandler();

        $health = $mw->process($this->request('GET', '/api/health'), $handler);
        $this->assertSame(204, $health->getStatusCode());

        $auth = $mw->process($this->request('GET', '/api/auth/google'), $handler);
        $this->assertSame(204, $auth->getStatusCode());

        $spa = $mw->process($this->request('GET', '/login'), $handler);
        $this->assertSame(204, $spa->getStatusCode());

        $me = $mw->process($this->request('GET', '/api/me'), $handler);
        $this->assertSame(401, $me->getStatusCode());
        $payload = json_decode((string) $me->getBody(), true);
        $this->assertSame('unauthenticated', $payload['error']['code']);

        $cookie = $sessions->create('0123456789abcdef0123456789abcdef');
        $authed = $mw->process(
            $this->request('GET', '/api/me')->withCookieParams(['sstf_session' => $cookie]),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $hash = $request->getAttribute('email_hash');
                    $response = new Response(200);
                    $response->getBody()->write(is_string($hash) ? $hash : '');

                    return $response;
                }
            },
        );
        $this->assertSame(200, $authed->getStatusCode());
        $this->assertSame('0123456789abcdef0123456789abcdef', (string) $authed->getBody());

        $this->deleteTree($tmp);
    }

    public function testAuthRateLimitSkipsNonAuthPathsAndTripsOnAuth(): void
    {
        $clock = new FakeClock(1_700);
        $limiter = new MemoryAuthRateLimiter(1, 60, $clock);
        $mw = new AuthRateLimit($limiter);
        $handler = $this->okHandler();

        $health = $mw->process($this->request('GET', '/api/health'), $handler);
        $this->assertSame(204, $health->getStatusCode());
        $me = $mw->process($this->request('GET', '/api/me'), $handler);
        $this->assertSame(204, $me->getStatusCode());

        $first = $mw->process($this->request('POST', '/api/auth/password'), $handler);
        $this->assertSame(204, $first->getStatusCode());
        $blocked = $mw->process($this->request('GET', '/api/auth/google'), $handler);
        $this->assertSame(429, $blocked->getStatusCode());
        $payload = json_decode((string) $blocked->getBody(), true);
        $this->assertSame('rate_limited', $payload['error']['code']);
        $this->assertSame('Too many attempts', $payload['error']['message']);
        $this->assertArrayNotHasKey('data', $payload);

        $logout = $mw->process($this->request('POST', '/api/auth/logout'), $handler);
        $this->assertSame(429, $logout->getStatusCode());
    }

    public function testAuthRateLimitFailsClosedWhenLimiterThrows(): void
    {
        $mw = new AuthRateLimit(new ThrowingAuthRateLimiter());
        $blocked = $mw->process($this->request('POST', '/api/auth/password'), $this->okHandler());
        $this->assertSame(429, $blocked->getStatusCode());
        $this->assertSame('rate_limited', json_decode((string) $blocked->getBody(), true)['error']['code']);
    }

    public function testAuthRateLimitUsesUnknownWhenRemoteAddrMissing(): void
    {
        $clock = new FakeClock(2_000);
        $mw = new AuthRateLimit(new MemoryAuthRateLimiter(1, 30, $clock));
        $handler = $this->okHandler();
        $withoutIp = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/password');
        $this->assertSame(204, $mw->process($withoutIp, $handler)->getStatusCode());
        $this->assertSame(429, $mw->process($withoutIp, $handler)->getStatusCode());
    }

    private function request(string $method, string $path): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, $path);
    }

    private function okHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };
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
