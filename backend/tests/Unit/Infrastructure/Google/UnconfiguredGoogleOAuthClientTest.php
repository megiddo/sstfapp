<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\Google;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Infrastructure\Google\UnconfiguredGoogleOAuthClient;

#[CoversClass(UnconfiguredGoogleOAuthClient::class)]
final class UnconfiguredGoogleOAuthClientTest extends TestCase
{
    public function testIsNotConfiguredAndRejectsCalls(): void
    {
        $client = new UnconfiguredGoogleOAuthClient();
        $this->assertFalse($client->isConfigured());
        try {
            $client->authorizationUrl('state');
            $this->fail('authorizationUrl');
        } catch (InvalidGoogleIdTokenException) {
        }

        $this->expectException(InvalidGoogleIdTokenException::class);
        $client->fetchUser('code');
    }
}
