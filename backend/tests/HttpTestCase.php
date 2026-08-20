<?php

declare(strict_types=1);

namespace Sstf\Api\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

abstract class HttpTestCase extends TestCase
{
    protected App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = require dirname(__DIR__) . '/config/bootstrap.php';
    }

    /**
     * @param array<string, mixed>|null $json
     * @param array<string, string> $headers
     */
    protected function request(
        string $method,
        string $uri,
        ?array $json = null,
        array $headers = [],
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($json !== null) {
            $body = (new StreamFactory())->createStream(
                (string) json_encode($json, JSON_THROW_ON_ERROR),
            );
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($body)
                ->withParsedBody($json);
        }

        return $this->app->handle($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(ResponseInterface $response): array
    {
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);

        return $payload;
    }
}
