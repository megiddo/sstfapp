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
use Sstf\Api\Http\Middleware\RequestLog;
use Sstf\Api\Http\Middleware\SecurityHeaders;
use Sstf\Api\Infrastructure\Log\JsonLogger;

#[CoversClass(SecurityHeaders::class)]
#[CoversClass(RequestLog::class)]
#[CoversClass(JsonLogger::class)]
final class SecurityHeadersTest extends TestCase
{
    public function testSecurityHeadersAreApplied(): void
    {
        $mw = new SecurityHeaders();
        $response = $mw->process(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/health'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(200);
                }
            },
        );

        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        $csp = $response->getHeaderLine('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('https://accounts.google.com', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertSame(SecurityHeaders::CSP, $csp);
    }

    public function testRequestLogGeneratesAndEchoesId(): void
    {
        $lines = [];
        $mw = new RequestLog(new JsonLogger(true, static function (string $line) use (&$lines): void {
            $lines[] = $line;
        }));
        $response = $mw->process(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/health'),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(204);
                }
            },
        );
        $id = $response->getHeaderLine(RequestLog::HEADER);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $id);
        $this->assertCount(1, $lines);
        $payload = json_decode(trim($lines[0]), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('http.request', $payload['event']);
        $this->assertSame('GET', $payload['method']);
        $this->assertSame('/api/health', $payload['path']);
        $this->assertSame(204, $payload['status']);
        $this->assertSame($id, $payload['request_id']);
        $this->assertArrayNotHasKey('email_hash', $payload);
        $this->assertArrayNotHasKey('email', $payload);
    }

    public function testRequestLogKeepsValidIncomingIdAndEmailHash(): void
    {
        $lines = [];
        $mw = new RequestLog(new JsonLogger(true, static function (string $line) use (&$lines): void {
            $lines[] = $line;
        }));
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/me')
            ->withHeader(RequestLog::HEADER, 'req-abc_1')
            ->withAttribute('email_hash', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
        $response = $mw->process($request, new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200);
            }
        });
        $this->assertSame('req-abc_1', $response->getHeaderLine(RequestLog::HEADER));
        $payload = json_decode(trim($lines[0]), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', $payload['email_hash']);
        $this->assertStringNotContainsString('@', $lines[0]);
    }

    public function testRequestLogRejectsInvalidIncomingIds(): void
    {
        $mw = new RequestLog(new JsonLogger(false, static function (string $line): void {
        }));
        foreach (['', 'has space', str_repeat('a', 65), 'bad/id'] as $bad) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', '/x');
            if ($bad !== '') {
                $request = $request->withHeader(RequestLog::HEADER, $bad);
            }
            $response = $mw->process($request, new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(204);
                }
            });
            $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $response->getHeaderLine(RequestLog::HEADER));
            $this->assertNotSame($bad, $response->getHeaderLine(RequestLog::HEADER));
        }
    }

    public function testBlankEmailHashIsOmitted(): void
    {
        $lines = [];
        $mw = new RequestLog(new JsonLogger(true, static function (string $line) use (&$lines): void {
            $lines[] = $line;
        }));
        $mw->process(
            (new ServerRequestFactory())->createServerRequest('GET', '/api/me')->withAttribute('email_hash', ''),
            new class implements RequestHandlerInterface {
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return new Response(401);
                }
            },
        );
        $payload = json_decode(trim($lines[0]), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('email_hash', $payload);
        $this->assertSame(401, $payload['status']);
    }
}
