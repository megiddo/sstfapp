<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Sstf\Api\Http\JsonErrorHandler;
use Sstf\Api\Http\JsonResponder;

#[CoversClass(JsonErrorHandler::class)]
#[CoversClass(JsonResponder::class)]
final class JsonErrorHandlerTest extends TestCase
{
    public function testHttpExceptionUsesStatusAndMessage(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/missing');
        $handler = new JsonErrorHandler(false);
        $response = $handler->handle(new HttpNotFoundException($request, 'Nope'));

        $this->assertSame(404, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('http_error', $payload['error']['code']);
        $this->assertSame('Nope', $payload['error']['message']);
    }

    public function testHttp400Stays400AndHttp599Stays599(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/x');
        $handler = new JsonErrorHandler(false);

        $four = $handler->handle(new HttpBadRequestException($request, 'bad'));
        $this->assertSame(400, $four->getStatusCode());
        $this->assertNotSame(500, $four->getStatusCode());

        $exception = new class ($request) extends \Slim\Exception\HttpException {
            public function __construct($request)
            {
                parent::__construct($request, 'edge', 599);
            }
        };
        $fiveNineNine = $handler->handle($exception);
        $this->assertSame(599, $fiveNineNine->getStatusCode());
        $this->assertNotSame(500, $fiveNineNine->getStatusCode());
    }

    public function testHttpExceptionWithEmptyMessageGetsFallback(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/missing');
        $handler = new JsonErrorHandler(false);
        $response = $handler->handle(new HttpNotFoundException($request, ''));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('HTTP error', $payload['error']['message']);
        $this->assertNotSame('', $payload['error']['message']);
    }

    public function testGenericExceptionHidesDetailsWhenNotDebugging(): void
    {
        $handler = new JsonErrorHandler(false);
        $response = $handler->handle(new RuntimeException('secret internals'));

        $this->assertSame(500, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('internal_error', $payload['error']['code']);
        $this->assertSame('Internal server error', $payload['error']['message']);
        $this->assertStringNotContainsString('secret', (string) $response->getBody());
    }

    public function testGenericExceptionShowsDetailsWhenDebugging(): void
    {
        $handler = new JsonErrorHandler(true);
        $response = $handler->handle(new RuntimeException('secret internals'));

        $this->assertSame(500, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('secret internals', $payload['error']['message']);
    }

    public function testEmptyGenericMessageGetsFallbackEvenWhenDebugging(): void
    {
        $handler = new JsonErrorHandler(true);
        $response = $handler->handle(new RuntimeException(''));

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Internal server error', $payload['error']['message']);
    }

    public function testInvokeUsesSlimDisplayFlagNotConstructor(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/x');
        $handler = new JsonErrorHandler(false);
        $response = $handler(
            $request,
            new RuntimeException('secret internals'),
            true,
            false,
            false,
        );

        $this->assertSame(500, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('secret internals', $payload['error']['message']);
    }

    public function testInvokeHidesDetailsWhenSlimSaysNotToDisplay(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/x');
        $handler = new JsonErrorHandler(true);
        $response = $handler(
            $request,
            new RuntimeException('secret internals'),
            false,
            false,
            false,
        );

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Internal server error', $payload['error']['message']);
        $this->assertStringNotContainsString('secret', (string) $response->getBody());
    }

    public function testHttpExceptionWithStatusAbove599Becomes500(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/x');
        $exception = new class ($request) extends \Slim\Exception\HttpException {
            public function __construct($request)
            {
                parent::__construct($request, 'range', 600);
            }
        };

        $handler = new JsonErrorHandler(false);
        $response = $handler->handle($exception);
        $this->assertSame(500, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('http_error', $payload['error']['code']);
        $this->assertSame('range', $payload['error']['message']);
    }

    public function testHttpExceptionWithInvalidCodeBecomes500(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/x');
        $exception = new class ($request) extends \Slim\Exception\HttpException {
            public function __construct($request)
            {
                parent::__construct($request, 'weird', 200);
            }
        };

        $handler = new JsonErrorHandler(false);
        $response = $handler->handle($exception);

        $this->assertSame(500, $response->getStatusCode());
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('http_error', $payload['error']['code']);
        $this->assertSame('weird', $payload['error']['message']);
    }
}
