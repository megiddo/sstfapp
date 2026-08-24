<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\Google;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Sstf\Api\Infrastructure\Google\OAuthStateService;
use Sstf\Api\Infrastructure\Session\SessionCookie;
use Sstf\Api\Tests\Fakes\FakeClock;

#[CoversClass(OAuthStateService::class)]
final class OAuthStateServiceTest extends TestCase
{
    public function testIssueReadExpireAndRejectsTamperedCookies(): void
    {
        $clock = new FakeClock(1_700_000_000);
        $service = new OAuthStateService(
            new SessionCookie(OAuthStateService::COOKIE, false),
            'testing-session-secret-key',
            $clock,
        );

        $issued = $service->issue('America/Chicago');
        $this->assertNotSame('', $issued['state']);
        $this->assertStringContainsString(OAuthStateService::COOKIE, $issued['header']);

        $value = $this->cookieValue($issued['header']);
        $factory = new ServerRequestFactory();
        $read = $service->read(
            $factory->createServerRequest('GET', '/callback')
                ->withCookieParams([OAuthStateService::COOKIE => $value]),
        );
        $this->assertNotNull($read);
        $this->assertSame($issued['state'], $read['state']);
        $this->assertSame('America/Chicago', $read['timezone']);

        $emptyTz = $service->issue(null);
        $emptyValue = $this->cookieValue($emptyTz['header']);
        $none = $service->read(
            $factory->createServerRequest('GET', '/callback')
                ->withCookieParams([OAuthStateService::COOKIE => $emptyValue]),
        );
        $this->assertNotNull($none);
        $this->assertNull($none['timezone']);

        $this->assertNull($service->read($factory->createServerRequest('GET', '/callback')));
        $this->assertNull($service->read(
            $factory->createServerRequest('GET', '/callback')
                ->withCookieParams([OAuthStateService::COOKIE => 'not-enough-parts']),
        ));
        $this->assertNull($service->read(
            $factory->createServerRequest('GET', '/callback')
                ->withCookieParams([OAuthStateService::COOKIE => 'state.notanumber.tz.hmac']),
        ));
        $this->assertNull($service->read(
            $factory->createServerRequest('GET', '/callback')
                ->withCookieParams([OAuthStateService::COOKIE => 'state.1700000600.tz.badhmac']),
        ));

        $clock->setTimestamp(1_700_000_601);
        $this->assertNull($service->read(
            $factory->createServerRequest('GET', '/callback')
                ->withCookieParams([OAuthStateService::COOKIE => $value]),
        ));

        $this->assertStringContainsString('Max-Age=0', $service->expireHeader());

        $this->assertNull($service->read(
            $factory->createServerRequest('GET', '/callback')
                ->withCookieParams([OAuthStateService::COOKIE => '.1700000600.tz.hmac']),
        ));
        $this->assertNull($service->read(
            $factory->createServerRequest('GET', '/callback')
                ->withCookieParams([OAuthStateService::COOKIE => 'state.1700000600.tz.']),
        ));
    }

    public function testConstructorRejectsShortSecretAndTtl(): void
    {
        $cookie = new SessionCookie(OAuthStateService::COOKIE, false);
        $clock = new FakeClock(1);
        try {
            new OAuthStateService($cookie, 'too-short', $clock);
            $this->fail('secret');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        new OAuthStateService($cookie, 'testing-session-secret-key', $clock, 0);
    }

    private function cookieValue(string $header): string
    {
        $pair = explode(';', $header, 2)[0];
        $eq = strpos($pair, '=');
        $this->assertNotFalse($eq);

        return substr($pair, $eq + 1);
    }
}
