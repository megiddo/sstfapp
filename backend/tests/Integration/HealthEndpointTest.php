<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use Sstf\Api\Http\Controllers\HealthController;
use Sstf\Api\Http\JsonErrorHandler;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\SqliteConnection;
use Sstf\Api\Services\HealthService;
use Sstf\Api\Tests\HttpTestCase;

#[CoversClass(HealthController::class)]
#[CoversClass(HealthService::class)]
#[CoversClass(JsonResponder::class)]
#[CoversClass(JsonErrorHandler::class)]
#[CoversClass(GlobalDb::class)]
#[CoversClass(Migrator::class)]
#[CoversClass(SqliteConnection::class)]
final class HealthEndpointTest extends HttpTestCase
{
    public function testHealthEndpointReturnsOkEnvelope(): void
    {
        $response = $this->request('GET', '/api/health');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = $this->json($response);
        $this->assertSame(['data' => ['ok' => true]], $payload);
        $this->assertArrayHasKey('data', $payload);
        $this->assertIsArray($payload['data']);
        $this->assertArrayHasKey('ok', $payload['data']);
        $this->assertTrue($payload['data']['ok']);
        $this->assertNotFalse($payload['data']['ok']);
        $this->assertArrayNotHasKey('error', $payload);
    }

    public function testHealthEndpointBodyIsExactJsonObject(): void
    {
        $response = $this->request('GET', '/api/health');
        $raw = (string) $response->getBody();

        $this->assertJsonStringEqualsJsonString('{"data":{"ok":true}}', $raw);
        $this->assertStringContainsString('"data"', $raw);
        $this->assertStringContainsString('"ok"', $raw);
        $this->assertStringContainsString('true', $raw);
    }

    public function testUnknownRouteReturnsJsonErrorEnvelope(): void
    {
        $response = $this->request('GET', '/api/does-not-exist');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $payload = $this->json($response);
        $this->assertArrayHasKey('error', $payload);
        $this->assertArrayNotHasKey('data', $payload);
        $this->assertIsArray($payload['error']);
        $this->assertSame('http_error', $payload['error']['code']);
        $this->assertIsString($payload['error']['message']);
        $this->assertNotSame('', $payload['error']['message']);
    }

    public function testHealthRejectsPost(): void
    {
        $response = $this->request('POST', '/api/health', []);

        $this->assertSame(405, $response->getStatusCode());
        $payload = $this->json($response);
        $this->assertSame('http_error', $payload['error']['code']);
    }
}
