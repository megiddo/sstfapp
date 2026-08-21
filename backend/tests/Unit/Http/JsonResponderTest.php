<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Http\JsonResponder;

#[CoversClass(JsonResponder::class)]
final class JsonResponderTest extends TestCase
{
    public function testDataWrapsPayloadInDesignEnvelope(): void
    {
        $response = JsonResponder::data(['ok' => true], 200);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"data":{"ok":true}}', (string) $response->getBody());
    }

    public function testDataHonorsCreatedStatus(): void
    {
        $response = JsonResponder::data(['id' => 7], 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('{"data":{"id":7}}', (string) $response->getBody());
    }

    public function testErrorUsesNestedCodeAndMessage(): void
    {
        $response = JsonResponder::error('not_found', 'Missing', 404);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(
            '{"error":{"code":"not_found","message":"Missing"}}',
            (string) $response->getBody(),
        );
    }

    public function testErrorAcceptsBoundary4xxAnd5xxStatuses(): void
    {
        $badRequest = JsonResponder::error('bad_request', 'Nope', 400);
        $this->assertSame(400, $badRequest->getStatusCode());

        $network = JsonResponder::error('timeout', 'Gateway', 599);
        $this->assertSame(599, $network->getStatusCode());
    }

    public function testErrorRejectsNonErrorStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Error responses must use an HTTP 4xx or 5xx status');
        JsonResponder::error('x', 'y', 200);
    }

    public function testErrorRejectsStatusBelow400(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        JsonResponder::error('x', 'y', 399);
    }

    public function testErrorRejectsStatusAbove599(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        JsonResponder::error('x', 'y', 600);
    }

    public function testJsonEncodesArbitraryPayload(): void
    {
        $response = JsonResponder::json(['n' => 1]);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"n":1}', (string) $response->getBody());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }
}
