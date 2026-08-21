<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\AccountExistsException;
use Sstf\Api\Domain\EmailUnverifiedException;
use Sstf\Api\Domain\InvalidCredentialsException;
use Sstf\Api\Domain\InvalidCurrentPasswordException;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Domain\InvalidPasswordException;
use Sstf\Api\Domain\InvalidTimezoneException;
use Sstf\Api\Domain\InvalidWeightUnitException;
use Sstf\Api\Domain\LoginTakenException;
use Sstf\Api\Domain\RepoKey;
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
#[CoversClass(AccountExistsException::class)]
#[CoversClass(LoginTakenException::class)]
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

        $hash = RepoKey::google('ada@example.com')->hash();
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
        $hash = RepoKey::google('me@example.com')->hash();

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
        $hash = RepoKey::google('bad@example.com')->hash();

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

    public function testRegisterThenGoogleCreatesSeparateRepos(): void
    {
        $service = $this->service(new FakeGoogleIdTokenVerifier());
        $created = $service->registerWithPassword('New@Example.COM', 'starter-pass', 'America/Chicago');
        $this->assertSame('New@Example.COM', $created['account']->email);
        $this->assertSame(['password'], $created['account']->providers);
        $passwordHash = RepoKey::password('New@Example.COM')->hash();
        $this->assertFileExists($this->tmp . '/users/' . $passwordHash . '.sqlite');

        $pdo = new \PDO('sqlite:' . $this->tmp . '/users/' . $passwordHash . '.sqlite');
        $stored = $pdo->query('SELECT password_hash FROM account WHERE id = 1')->fetchColumn();
        $this->assertIsString($stored);
        $this->assertSame(PASSWORD_ARGON2ID, password_get_info($stored)['algo']);

        $login = $service->signInWithPassword(' new@example.com ', 'starter-pass');
        $this->assertSame('New@Example.COM', $login['account']->email);
        $this->assertSame(['password'], $login['account']->providers);

        $verifier = new FakeGoogleIdTokenVerifier();
        $verifier->willVerify('g', FakeGoogleIdTokenVerifier::user('New@Example.COM', true, 'sub-n'));
        $googleService = $this->service($verifier);
        $google = $googleService->signInWithGoogle('g', 'Europe/Paris');
        $this->assertSame(['google'], $google['account']->providers);
        $this->assertSame('Europe/Paris', $google['account']->timezone);
        $googleHash = RepoKey::google('New@Example.COM')->hash();
        $this->assertFileExists($this->tmp . '/users/' . $googleHash . '.sqlite');
        $this->assertNotSame($passwordHash, $googleHash);
        $this->assertCount(2, glob($this->tmp . '/users/*.sqlite') ?: []);
    }

    public function testPasswordLoginRejectsUnknownWrongAndInvalidEmail(): void
    {
        $service = $this->service(new FakeGoogleIdTokenVerifier());
        $service->registerWithPassword('ok@example.com', 'right-pass', null);

        try {
            $service->signInWithPassword('ok@example.com', 'wrong-pass');
            $this->fail('wrong password');
        } catch (InvalidCredentialsException) {
        }

        try {
            $service->signInWithPassword('missing@example.com', 'right-pass');
            $this->fail('unknown email');
        } catch (InvalidCredentialsException) {
        }

        try {
            $service->signInWithPassword('nope!', 'right-pass');
            $this->fail('invalid username');
        } catch (InvalidCredentialsException) {
        }

        $this->expectException(InvalidCredentialsException::class);
        $service->signInWithPassword('missing@example.com', 'right-pass');
    }

    public function testRegisterRejectsEmptyPasswordInvalidEmailAndExistingFile(): void
    {
        $service = $this->service(new FakeGoogleIdTokenVerifier());
        try {
            $service->registerWithPassword('a@b.com', '', null);
            $this->fail('empty password');
        } catch (InvalidPasswordException) {
        }

        try {
            $service->registerWithPassword('nope!', 'secret', null);
            $this->fail('invalid username');
        } catch (InvalidCredentialsException) {
        }

        $service->registerWithPassword('dup@example.com', 'secret', null);
        $this->expectException(AccountExistsException::class);
        $service->registerWithPassword('dup@example.com', 'other', null);
    }

    public function testSetAndChangePassword(): void
    {
        $verifier = new FakeGoogleIdTokenVerifier();
        $verifier->willVerify('tok', FakeGoogleIdTokenVerifier::user('pw@example.com'));
        $service = $this->service($verifier);
        $service->signInWithGoogle('tok', 'UTC');
        $hash = RepoKey::google('pw@example.com')->hash();

        $set = $service->updateMe($hash, null, null, 'first-pass', null);
        $this->assertContains('password', $set->providers);
        $this->assertContains('google', $set->providers);

        try {
            $service->updateMe($hash, null, null, 'second-pass', null);
            $this->fail('missing current');
        } catch (InvalidCurrentPasswordException) {
        }

        try {
            $service->updateMe($hash, null, null, 'second-pass', '');
            $this->fail('empty current');
        } catch (InvalidCurrentPasswordException) {
        }

        try {
            $service->updateMe($hash, null, null, 'second-pass', 'nope');
            $this->fail('wrong current');
        } catch (InvalidCurrentPasswordException) {
        }

        try {
            $service->updateMe($hash, null, null, '', 'first-pass');
            $this->fail('empty new');
        } catch (InvalidPasswordException) {
        }

        $changed = $service->updateMe($hash, null, null, 'second-pass', 'first-pass');
        $this->assertContains('password', $changed->providers);
        $login = $service->signInWithPassword('pw@example.com', 'second-pass');
        $this->assertSame('pw@example.com', $login['account']->email);
    }

    public function testSetPasswordOnGoogleFailsWhenPasswordUsernameIsTaken(): void
    {
        $service = $this->service(new FakeGoogleIdTokenVerifier());
        $service->registerWithPassword('taken@example.com', 'pass-one', null);

        $verifier = new FakeGoogleIdTokenVerifier();
        $verifier->willVerify('tok', FakeGoogleIdTokenVerifier::user('taken@example.com'));
        $googleService = $this->service($verifier);
        $googleService->signInWithGoogle('tok', 'UTC');
        $hash = RepoKey::google('taken@example.com')->hash();

        $this->expectException(AccountExistsException::class);
        $googleService->updateMe($hash, null, null, 'pass-two', null);
    }

    public function testGoogleOnlyPasswordLoginFails(): void
    {
        $verifier = new FakeGoogleIdTokenVerifier();
        $verifier->willVerify('tok', FakeGoogleIdTokenVerifier::user('gop@example.com'));
        $service = $this->service($verifier);
        $service->signInWithGoogle('tok', null);
        $this->expectException(InvalidCredentialsException::class);
        $service->signInWithPassword('gop@example.com', 'anything');
    }

    public function testDirectoryPasswordHelpersOnMissingAndExistingFiles(): void
    {
        $verifier = new FakeGoogleIdTokenVerifier();
        $verifier->willVerify('tok', FakeGoogleIdTokenVerifier::user('dir@example.com'));
        $migrator = new Migrator();
        $root = dirname(__DIR__, 3);
        $users = new UserDbFactory($this->tmp . '/users', $migrator, $root . '/migrations/user');
        $global = new GlobalDb($this->tmp . '/global.sqlite', $migrator, $root . '/migrations/global');
        $directory = new UserDirectory($users, $global, new SystemClock());
        $missing = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

        $this->assertFalse($directory->userFileExists($missing));
        $this->assertNull($directory->passwordHash($missing));
        $this->assertNull($directory->setPasswordHash($missing, 'hash'));

        $service = $this->service($verifier);
        $service->signInWithGoogle('tok', 'UTC');
        $hash = RepoKey::google('dir@example.com')->hash();
        $this->assertTrue($directory->userFileExists($hash));
        $this->assertNull($directory->passwordHash($hash));

        $updated = $directory->provisionPasswordUser($hash, 'dir@example.com', 'dir@example.com', 'not-a-real-hash', null);
        $this->assertContains('password', $updated->providers);
        $this->assertContains('google', $updated->providers);
        $this->assertSame('not-a-real-hash', $directory->passwordHash($hash));

        $emptyHash = str_repeat('b', 32);
        $users->open($emptyHash);
        $this->assertTrue($directory->userFileExists($emptyHash));
        $this->assertNull($directory->passwordHash($emptyHash));
        $this->assertNull($directory->loadAccount($emptyHash));

        $pdo = new \PDO('sqlite:' . $this->tmp . '/users/' . $hash . '.sqlite');
        $pdo->exec("UPDATE account SET password_hash = ''");
        $this->assertNull($directory->passwordHash($hash));
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

        return new AuthService(
            $verifier,
            $directory,
            $sessions,
            [
                'memory_cost' => 16,
                'time_cost' => 1,
                'threads' => 1,
            ],
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
