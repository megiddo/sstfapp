<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\Session;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Sstf\Api\Infrastructure\Session\FileSessionStore;
use Sstf\Api\Infrastructure\Session\SessionCookie;
use Sstf\Api\Infrastructure\Session\SessionService;

#[CoversClass(SessionService::class)]
#[CoversClass(FileSessionStore::class)]
#[CoversClass(SessionCookie::class)]
final class SessionServiceTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-ssvc-' . getmypid() . '-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testCreateRoundTripAndHmac(): void
    {
        $service = $this->service();
        $hash = '0123456789abcdef0123456789abcdef';
        $cookie = $service->create($hash);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}\.[a-f0-9]{64}$/', $cookie);
        $this->assertSame($hash, $service->emailHashFromCookie($cookie));
        $this->assertNull($service->emailHashFromCookie(null));
        $this->assertNull($service->emailHashFromCookie(''));
        $this->assertNull($service->emailHashFromCookie('not-a-cookie'));
        $this->assertNull($service->emailHashFromCookie(str_repeat('a', 64) . '.' . str_repeat('b', 64)));

        $tampered = substr($cookie, 0, -1) . ($cookie[-1] === 'a' ? 'b' : 'a');
        $this->assertNull($service->emailHashFromCookie($tampered));

        $service->destroy($cookie);
        $this->assertNull($service->emailHashFromCookie($cookie));
        $service->destroy(null);
        $service->destroy('nope');
    }

    public function testCookieValueFromRequest(): void
    {
        $service = $this->service();
        $empty = (new ServerRequestFactory())->createServerRequest('GET', '/api/me');
        $this->assertNull($service->cookieValueFrom($empty));

        $with = $empty->withCookieParams(['sstf_session' => 'abc.def']);
        $this->assertSame('abc.def', $service->cookieValueFrom($with));

        $blank = $empty->withCookieParams(['sstf_session' => '']);
        $this->assertNull($service->cookieValueFrom($blank));
    }

    public function testSetAndExpireHeaders(): void
    {
        $service = $this->service();
        $set = $service->setCookieHeader('abc.def');
        $this->assertStringContainsString('sstf_session=abc.def', $set);
        $this->assertStringContainsString('HttpOnly', $set);
        $this->assertStringContainsString('SameSite=Lax', $set);
        $this->assertStringNotContainsString('Secure', $set);

        $expire = $service->expireCookieHeader();
        $this->assertStringContainsString('Max-Age=0', $expire);
    }

    public function testRejectsShortSecretAndBadHash(): void
    {
        try {
            new SessionService(
                new FileSessionStore($this->tmp),
                new SessionCookie('sstf_session', false),
                'short',
            );
            $this->fail('short secret');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('SESSION_SECRET', $e->getMessage());
        }

        $service = $this->service();
        $this->expectException(InvalidArgumentException::class);
        $service->create('not-a-hash');
    }

    private function service(): SessionService
    {
        return new SessionService(
            new FileSessionStore($this->tmp),
            new SessionCookie('sstf_session', false),
            'testing-session-secret-key',
        );
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = scandir($path);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . '/' . $item;
            if (is_dir($full)) {
                $this->deleteTree($full);
            } else {
                unlink($full);
            }
        }
        rmdir($path);
    }
}
