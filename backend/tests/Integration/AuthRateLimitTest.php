<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use Sstf\Api\Http\Controllers\AuthController;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Http\Middleware\AuthRateLimit;
use Sstf\Api\Infrastructure\RateLimit\MemoryAuthRateLimiter;
use Sstf\Api\Tests\HttpTestCase;

#[CoversClass(AuthRateLimit::class)]
#[CoversClass(MemoryAuthRateLimiter::class)]
#[CoversClass(AuthController::class)]
#[CoversClass(JsonResponder::class)]
final class AuthRateLimitTest extends HttpTestCase
{
    protected function rateLimitMax(): int
    {
        return 3;
    }

    public function testAuthRoutesReturn429AfterMaxAttempts(): void
    {
        $body = [
            'email' => 'nobody-' . bin2hex(random_bytes(4)) . '@example.com',
            'password' => 'wrong',
        ];

        $first = $this->request('POST', '/api/auth/password', $body);
        $this->assertSame(401, $first->getStatusCode());
        $second = $this->request('POST', '/api/auth/password', $body);
        $this->assertSame(401, $second->getStatusCode());
        $third = $this->request('POST', '/api/auth/password', $body);
        $this->assertSame(401, $third->getStatusCode());

        $blocked = $this->request('POST', '/api/auth/password', $body);
        $this->assertSame(429, $blocked->getStatusCode());
        $payload = $this->json($blocked);
        $this->assertSame('rate_limited', $payload['error']['code']);
        $this->assertSame('Too many attempts', $payload['error']['message']);
        $this->assertArrayNotHasKey('data', $payload);
        $this->assertStringNotContainsString('wrong', (string) $blocked->getBody());
        $this->assertStringNotContainsString($body['email'], (string) $blocked->getBody());

        $google = $this->request('POST', '/api/auth/google', ['id_token' => 'anything']);
        $this->assertSame(429, $google->getStatusCode());
        $this->assertSame('rate_limited', $this->json($google)['error']['code']);
    }

    public function testHealthIsNotRateLimited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->request('GET', '/api/health');
            $this->assertSame(200, $response->getStatusCode());
        }
    }
}
