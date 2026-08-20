<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Http\Controllers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Sstf\Api\Http\Controllers\HealthController;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Services\HealthService;

#[CoversClass(HealthController::class)]
#[CoversClass(HealthService::class)]
#[CoversClass(JsonResponder::class)]
final class HealthControllerTest extends TestCase
{
    public function testInvokeWritesDataOkEnvelope(): void
    {
        $controller = new HealthController(new HealthService());
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');
        $response = $controller($request, new Response());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"data":{"ok":true}}', (string) $response->getBody());
    }

    public function testInvokeUsesHealthServicePayload(): void
    {
        $service = new HealthService();
        $controller = new HealthController($service);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');
        $response = $controller($request, new Response());

        $this->assertJsonStringEqualsJsonString(
            (string) json_encode(['data' => $service->status()], JSON_THROW_ON_ERROR),
            (string) $response->getBody(),
        );
        $this->assertTrue($service->status()['ok']);
        $this->assertTrue($service->isHealthy());
    }
}
