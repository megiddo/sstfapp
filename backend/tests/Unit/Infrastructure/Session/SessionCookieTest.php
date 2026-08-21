<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\Session;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Infrastructure\Session\SessionCookie;

#[CoversClass(SessionCookie::class)]
final class SessionCookieTest extends TestCase
{
    public function testDevelopmentCookieIsHttpOnlySameSiteLaxNotSecure(): void
    {
        $cookie = new SessionCookie('sstf_session', false);
        $header = $cookie->header('abc.def', 3600);

        $this->assertSame('sstf_session', $cookie->name());
        $this->assertStringStartsWith('sstf_session=abc.def;', $header);
        $this->assertStringContainsString('Path=/', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('SameSite=Lax', $header);
        $this->assertStringContainsString('Max-Age=3600', $header);
        $this->assertStringNotContainsString('Secure', $header);
        $this->assertStringNotContainsString('SameSite=Strict', $header);
        $this->assertStringNotContainsString('SameSite=None', $header);
    }

    public function testProductionCookieIncludesSecure(): void
    {
        $cookie = new SessionCookie('sstf_session', true);
        $header = $cookie->header('token', 10);

        $this->assertStringContainsString('Secure', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('SameSite=Lax', $header);
        $this->assertNotSame($cookie->header('token', 10), (new SessionCookie('sstf_session', false))->header('token', 10));
    }

    public function testExpireClearsValueAndMaxAgeZero(): void
    {
        $cookie = new SessionCookie('sstf_session', true);
        $header = $cookie->expireHeader();

        $this->assertStringStartsWith('sstf_session=;', $header);
        $this->assertStringContainsString('Max-Age=0', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('Secure', $header);
    }

    public function testEmptyNameRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cookie name cannot be empty');
        new SessionCookie('', false);
    }

    public function testNegativeMaxAgeRejected(): void
    {
        $cookie = new SessionCookie('sstf_session', false);
        $this->expectException(InvalidArgumentException::class);
        $cookie->header('x', -1);
    }
}
