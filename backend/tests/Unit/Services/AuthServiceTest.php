<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\EmailUnverifiedException;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Domain\InvalidTimezoneException;
use Sstf\Api\Domain\InvalidWeightUnitException;
use Sstf\Api\Domain\SystemClock;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Infrastructure\Session\FileSessionStore;
use Sstf\Api\Infrastructure\Session\SessionCookie;
use Sstf\Api\Infrastructure\Session\SessionService;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;
use Sstf\Api\Services\AuthService;
use Sstf\Api\Tests\Fakes\FakeGoogleIdTokenVerifier;

#[CoversClass(AuthService::class)]
#[CoversClass(UserDirectory::class)]
final class AuthServiceTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-auth-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/users', 0700, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testSignInCreatesAccountAndMeLoadsIt(): void
    {
        $verifier = new FakeGoogleIdTokenVerifier();
        $verifier->willVerify('tok', FakeGoogleIdTokenVerifier::user('Ada@Example.COM', true, 'sub-1'));
        $service = $this->service($verifier);

        $result = $service->signInWithGoogle('tok', 'America/Chicago');
        $this->assertSame('Ada@Example.COM', $result['account']->email);
        $this->assertSame('America/Chicago', $result['account']->timezone);
        $this->assertSame(['google'], $result['account']->providers);
        $this->assertNotSame('', $result['cookie']);

        $hash = md5('ada@example.com');
        $me = $service->me($hash);
        $this->assertSame('Ada@Example.COM', $me->email);
        $this->assertSame('lb', $me->weightUnit);

        $service->logout($result['cookie']);
    }

    public function testUnverifiedEmailIsRejected(): void
    {
        $verifier = new FakeGoogleIdTokenVerifier();
        $verifier->willVerify('tok', FakeGoogleIdTokenVerifier::user('a@b.com', false));
        $service = $this->service($verifier);

        $this->expectException(EmailUnverifiedException::class);
        $service->signInWithGoogle('tok', null);
    }

    public function testInvalidTokenAndInvalidEmail(): void
    {
        $verifier = new FakeGoogleIdTokenVerifier();
        $service = $this->service($verifier);

        try {
            $service->signInWithGoogle('missing', null);
            $this->fail('missing token');
        } catch (InvalidGoogleIdTokenException) {
        }

        $verifier->willVerify('bad-email', FakeGoogleIdTokenVerifier::user('no-at-sign', true));
        $this->expectException(InvalidGoogleIdTokenException::class);
        $service->signInWithGoogle('bad-email', null);
    }

    public function testMeMissingAccountIsUnauthenticated(): void
    {
        $service = $this->service(new FakeGoogleIdTokenVerifier());
        $this->expectException(UnauthenticatedException::class);
        $service->me('0123456789abcdef0123456789abcdef');
    }

    public function testUpdateMeTimezoneAndUnit(): void
    {
        $verifier = new FakeGoogleIdTokenVerifier();
        $verifier->willVerify('tok', FakeGoogleIdTokenVerifier::user('me@example.com'));
        $service = $this->service($verifier);
        $service->signInWithGoogle('tok', 'America/Chicago');
        $hash = md5('me@example.com');

        $tz = $service->updateMe($hash, 'Europe/Paris', null);
        $this->assertSame('Europe/Paris', $tz->timezone);
        $this->assertSame('lb', $tz->weightUnit);

        $kg = $service->updateMe($hash, null, 'kg');
        $this->assertSame('Europe/Paris', $kg->timezone);
        $this->assertSame('kg', $kg->weightUnit);

        $both = $service->updateMe($hash, 'UTC', 'lb');
        $this->assertSame('UTC', $both->timezone);
        $this->assertSame('lb', $both->weightUnit);

        $noop = $service->updateMe($hash, null, null);
        $this->assertSame('UTC', $noop->timezone);
        $this->assertSame('lb', $noop->weightUnit);
    }

    public function testUpdateMeRejectsInvalidTimezoneAndUnit(): void
    {
        $verifier = new FakeGoogleIdTokenVerifier();
        $verifier->willVerify('tok', FakeGoogleIdTokenVerifier::user('bad@example.com'));
        $service = $this->service($verifier);
        $service->signInWithGoogle('tok', 'UTC');
        $hash = md5('bad@example.com');

        try {
            $service->updateMe($hash, 'Not/A_Zone', null);
            $this->fail('timezone');
        } catch (InvalidTimezoneException) {
        }

        try {
            $service->updateMe($hash, '', null);
            $this->fail('empty timezone');
        } catch (InvalidTimezoneException) {
        }

        $this->expectException(InvalidWeightUnitException::class);
        $service->updateMe($hash, null, 'st');
    }

    public function testUpdateMeMissingAccountIsUnauthenticated(): void
    {
        $service = $this->service(new FakeGoogleIdTokenVerifier());
        $this->expectException(UnauthenticatedException::class);
        $service->updateMe('0123456789abcdef0123456789abcdef', 'UTC', 'kg');
    }

    private function service(FakeGoogleIdTokenVerifier $verifier): AuthService
    {
        $migrator = new Migrator();
        $root = dirname(__DIR__, 3);
        $users = new UserDbFactory($this->tmp . '/users', $migrator, $root . '/migrations/user');
        $global = new GlobalDb($this->tmp . '/global.sqlite', $migrator, $root . '/migrations/global');
        $directory = new UserDirectory($users, $global, new SystemClock());
        $sessions = new SessionService(
            new FileSessionStore($this->tmp . '/sessions'),
            new SessionCookie('sstf_session', false),
            'testing-session-secret-key',
        );

        return new AuthService($verifier, $directory, $sessions);
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
