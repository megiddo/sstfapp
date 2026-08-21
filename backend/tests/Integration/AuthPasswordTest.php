<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Integration;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use Sstf\Api\Domain\AccountExistsException;
use Sstf\Api\Domain\InvalidCredentialsException;
use Sstf\Api\Domain\InvalidCurrentPasswordException;
use Sstf\Api\Domain\InvalidPasswordException;
use Sstf\Api\Http\Controllers\AuthController;
use Sstf\Api\Http\Controllers\MeController;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Http\Middleware\AuthRateLimit;
use Sstf\Api\Http\Middleware\RequireJsonContentType;
use Sstf\Api\Http\Middleware\SessionAuth;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;
use Sstf\Api\Services\AuthService;
use Sstf\Api\Tests\Fakes\FakeGoogleIdTokenVerifier;
use Sstf\Api\Tests\HttpTestCase;

#[CoversClass(AccountExistsException::class)]
#[CoversClass(AuthController::class)]
#[CoversClass(MeController::class)]
#[CoversClass(AuthService::class)]
#[CoversClass(UserDirectory::class)]
#[CoversClass(SessionAuth::class)]
#[CoversClass(AuthRateLimit::class)]
#[CoversClass(RequireJsonContentType::class)]
#[CoversClass(JsonResponder::class)]
#[CoversClass(InvalidCredentialsException::class)]
#[CoversClass(InvalidPasswordException::class)]
#[CoversClass(InvalidCurrentPasswordException::class)]
final class AuthPasswordTest extends HttpTestCase
{
    public function testGoogleThenPasswordOpensTheSameFileWithSchedulesAndLogs(): void
    {
        $email = 'gp-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email, 'America/Chicago');

        $schedule = $this->json($this->request('POST', '/api/schedules', ['name' => 'Hypertrophy']))['data'];
        $set = $this->json($this->request('POST', '/api/schedules/' . $schedule['id'] . '/sets', [
            'name' => 'Evening',
            'day_of_week' => 3,
            'start_minutes' => 1080,
        ]))['data'];
        $catalog = $this->json($this->request('GET', '/api/exercises'))['data']['exercises'];
        $benchId = null;
        foreach ($catalog as $row) {
            if ($row['name'] === 'Bench Press') {
                $benchId = $row['id'];
                break;
            }
        }
        $this->assertNotNull($benchId);
        $this->request('PUT', '/api/sets/' . $set['id'] . '/exercises', [
            'exercises' => [['global_exercise_id' => $benchId]],
        ]);
        $logged = $this->request('POST', '/api/logs', [
            'set_id' => $set['id'],
            'global_exercise_id' => $benchId,
            'weight' => 190,
            'reps' => 6,
        ]);
        $this->assertSame(200, $logged->getStatusCode());

        $path = $this->userDbPath($email);
        $this->assertFileExists($path);

        $patched = $this->request('PATCH', '/api/me', ['password' => 'correct-horse']);
        $this->assertSame(200, $patched->getStatusCode());
        $me = $this->json($patched)['data'];
        $this->assertArrayNotHasKey('password_hash', $me);
        $this->assertSame($email, $me['email']);
        $providers = [];
        foreach ($me['identities'] as $identity) {
            $providers[] = $identity['provider'];
        }
        $this->assertContains('google', $providers);
        $this->assertContains('password', $providers);
        $this->assertStringNotContainsString('correct-horse', (string) $patched->getBody());
        $this->assertStringNotContainsString('password_hash', (string) $patched->getBody());

        $pdo = new PDO('sqlite:' . $path);
        $hash = $pdo->query('SELECT password_hash FROM account WHERE id = 1')->fetchColumn();
        $this->assertIsString($hash);
        $info = password_get_info($hash);
        $this->assertSame(PASSWORD_ARGON2ID, $info['algo']);
        $this->assertSame('argon2id', $info['algoName']);

        $logout = $this->request('POST', '/api/auth/logout', []);
        $this->assertSame(200, $logout->getStatusCode());

        $login = $this->request('POST', '/api/auth/password', [
            'email' => '  ' . strtoupper($email) . '  ',
            'password' => 'correct-horse',
        ]);
        $this->assertSame(200, $login->getStatusCode());
        $this->assertNotSame('', $login->getHeaderLine('Set-Cookie'));
        $this->assertSame($email, $this->json($login)['data']['email']);
        $this->assertArrayNotHasKey('password_hash', $this->json($login)['data']);

        $this->assertCount(1, glob($this->dataDir . '/users/*.sqlite') ?: []);
        $this->assertFileExists($path);

        $schedules = $this->json($this->request('GET', '/api/schedules'))['data']['schedules'];
        $this->assertCount(1, $schedules);
        $this->assertSame('Hypertrophy', $schedules[0]['name']);
        $this->assertTrue($schedules[0]['is_active']);

        $logs = $this->json($this->request('GET', '/api/logs'))['data'];
        $this->assertNotSame([], $logs['days']);
        $this->assertSame('Bench Press', $logs['days'][0]['logs'][0]['exercise_name']);
        $this->assertEquals(190, $logs['days'][0]['logs'][0]['weight']);
    }

    public function testSetPasswordOnGoogleConflictsWhenPasswordUsernameExists(): void
    {
        $email = 'taken-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->assertSame(200, $this->request('POST', '/api/auth/register', [
            'username' => $email,
            'password' => 'password-repo',
        ])->getStatusCode());
        $this->request('POST', '/api/auth/logout', []);

        $this->signIn($email);
        $conflict = $this->request('PATCH', '/api/me', ['password' => 'google-repo']);
        $this->assertSame(409, $conflict->getStatusCode());
        $this->assertSame('account_exists', $this->json($conflict)['error']['code']);
        $this->assertCount(2, glob($this->dataDir . '/users/*.sqlite') ?: []);
    }

    public function testPasswordFirstThenGoogleCreatesSeparateFiles(): void
    {
        $email = 'pg-' . bin2hex(random_bytes(4)) . '@example.com';
        $container = $this->app->getContainer();
        $this->assertNotNull($container);
        $auth = $container->get(AuthService::class);
        $created = $auth->registerWithPassword($email, 'first-password', 'America/Chicago');
        $this->assertSame($email, $created['account']->email);
        $this->assertSame(['password'], $created['account']->providers);

        $passwordPath = $this->userDbPath($email, 'password');
        $this->assertFileExists($passwordPath);
        $this->assertCount(1, glob($this->dataDir . '/users/*.sqlite') ?: []);

        $pdo = new PDO('sqlite:' . $passwordPath);
        $hash = $pdo->query('SELECT password_hash FROM account WHERE id = 1')->fetchColumn();
        $this->assertIsString($hash);
        $this->assertSame(PASSWORD_ARGON2ID, password_get_info($hash)['algo']);

        $this->cookies = [];
        $this->googleVerifier->willVerify('pg-token', FakeGoogleIdTokenVerifier::user($email, true, 'sub-pg'));
        $google = $this->request('POST', '/api/auth/google', [
            'id_token' => 'pg-token',
            'timezone' => 'Europe/Paris',
        ]);
        $this->assertSame(200, $google->getStatusCode());
        $me = $this->json($google)['data'];
        $this->assertSame($email, $me['email']);
        $this->assertSame('Europe/Paris', $me['timezone']);
        $this->assertArrayNotHasKey('password_hash', $me);
        $this->assertSame(['google'], array_column($me['identities'], 'provider'));

        $googlePath = $this->userDbPath($email);
        $this->assertFileExists($googlePath);
        $this->assertNotSame($passwordPath, $googlePath);
        $this->assertCount(2, glob($this->dataDir . '/users/*.sqlite') ?: []);

        $pdo = new PDO('sqlite:' . $googlePath);
        $subject = $pdo->query("SELECT provider_subject FROM identities WHERE provider = 'google'")->fetchColumn();
        $this->assertSame('sub-pg', $subject);
        $passwordRows = (int) $pdo->query("SELECT COUNT(*) FROM identities WHERE provider = 'password'")->fetchColumn();
        $this->assertSame(0, $passwordRows);
    }

    public function testUnverifiedGoogleIsStillRejectedAfterPasswordProvision(): void
    {
        $email = 'unv-' . bin2hex(random_bytes(4)) . '@example.com';
        $container = $this->app->getContainer();
        $this->assertNotNull($container);
        $container->get(AuthService::class)->registerWithPassword($email, 'secret-pass', null);

        $this->googleVerifier->willVerify(
            'unv-token',
            FakeGoogleIdTokenVerifier::user($email, false, 'sub-unv'),
        );
        $response = $this->request('POST', '/api/auth/google', ['id_token' => 'unv-token']);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('email_unverified', $this->json($response)['error']['code']);
        $this->assertSame('Email not verified', $this->json($response)['error']['message']);

        $pdo = new PDO('sqlite:' . $this->userDbPath($email, 'password'));
        $googleCount = (int) $pdo->query("SELECT COUNT(*) FROM identities WHERE provider = 'google'")->fetchColumn();
        $this->assertSame(0, $googleCount);
    }

    public function testWrongPasswordAndUnknownEmailShareTheSame401(): void
    {
        $email = 'same-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email);
        $this->request('PATCH', '/api/me', ['password' => 'correct-horse']);
        $this->request('POST', '/api/auth/logout', []);

        $wrong = $this->request('POST', '/api/auth/password', [
            'email' => $email,
            'password' => 'not-the-password',
        ]);
        $this->assertSame(401, $wrong->getStatusCode());
        $wrongBody = $this->json($wrong);
        $this->assertSame('invalid_credentials', $wrongBody['error']['code']);
        $this->assertSame('Sign-in failed', $wrongBody['error']['message']);
        $this->assertArrayNotHasKey('data', $wrongBody);
        $this->assertStringNotContainsString('not-the-password', (string) $wrong->getBody());
        $this->assertStringNotContainsString('password_hash', (string) $wrong->getBody());

        $unknownEmail = 'missing-' . bin2hex(random_bytes(4)) . '@example.com';
        $unknown = $this->request('POST', '/api/auth/password', [
            'email' => $unknownEmail,
            'password' => 'not-the-password',
        ]);
        $this->assertSame(401, $unknown->getStatusCode());
        $unknownBody = $this->json($unknown);
        $this->assertSame($wrongBody['error']['code'], $unknownBody['error']['code']);
        $this->assertSame($wrongBody['error']['message'], $unknownBody['error']['message']);
        $this->assertSame($wrong->getStatusCode(), $unknown->getStatusCode());
        $this->assertFileDoesNotExist($this->userDbPath($unknownEmail));
        $this->assertFileDoesNotExist($this->userDbPath($unknownEmail, 'password'));

        $googleOnly = 'gonly-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($googleOnly);
        $this->request('POST', '/api/auth/logout', []);
        $noPassword = $this->request('POST', '/api/auth/password', [
            'email' => $googleOnly,
            'password' => 'anything',
        ]);
        $this->assertSame(401, $noPassword->getStatusCode());
        $this->assertSame('invalid_credentials', $this->json($noPassword)['error']['code']);
        $this->assertSame('Sign-in failed', $this->json($noPassword)['error']['message']);
    }

    public function testMeNeverIncludesPasswordHashAfterPasswordIsSet(): void
    {
        $email = 'mehash-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email);
        $this->request('PATCH', '/api/me', ['password' => 'keep-secret']);
        $me = $this->request('GET', '/api/me');
        $payload = $this->json($me);
        $this->assertSame(200, $me->getStatusCode());
        $this->assertArrayNotHasKey('password_hash', $payload['data']);
        $encoded = (string) $me->getBody();
        $this->assertStringNotContainsString('password_hash', $encoded);
        $this->assertStringNotContainsString('keep-secret', $encoded);
        $this->assertStringNotContainsString('$argon2id$', $encoded);
    }

    public function testChangePasswordRequiresCurrentPassword(): void
    {
        $email = 'chg-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email);
        $this->assertSame(200, $this->request('PATCH', '/api/me', ['password' => 'first-secret'])->getStatusCode());

        $missing = $this->request('PATCH', '/api/me', ['password' => 'second-secret']);
        $this->assertSame(400, $missing->getStatusCode());
        $this->assertSame('invalid_current_password', $this->json($missing)['error']['code']);
        $this->assertStringNotContainsString('first-secret', (string) $missing->getBody());
        $this->assertStringNotContainsString('second-secret', (string) $missing->getBody());

        $wrong = $this->request('PATCH', '/api/me', [
            'password' => 'second-secret',
            'current_password' => 'nope',
        ]);
        $this->assertSame(400, $wrong->getStatusCode());
        $this->assertSame('invalid_current_password', $this->json($wrong)['error']['code']);

        $ok = $this->request('PATCH', '/api/me', [
            'password' => 'second-secret',
            'current_password' => 'first-secret',
        ]);
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertArrayNotHasKey('password_hash', $this->json($ok)['data']);

        $this->request('POST', '/api/auth/logout', []);
        $old = $this->request('POST', '/api/auth/password', [
            'email' => $email,
            'password' => 'first-secret',
        ]);
        $this->assertSame(401, $old->getStatusCode());
        $new = $this->request('POST', '/api/auth/password', [
            'email' => $email,
            'password' => 'second-secret',
        ]);
        $this->assertSame(200, $new->getStatusCode());
    }

    public function testEmptyPasswordPatchIsRejected(): void
    {
        $email = 'empty-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email);
        $response = $this->request('PATCH', '/api/me', ['password' => '']);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('invalid_password', $this->json($response)['error']['code']);
    }

    public function testPasswordLoginRequiresJsonAndRejectsMalformedBodies(): void
    {
        $noJson = $this->request('POST', '/api/auth/password');
        $this->assertSame(415, $noJson->getStatusCode());

        $empty = $this->request('POST', '/api/auth/password', ['email' => '', 'password' => 'x']);
        $this->assertSame(400, $empty->getStatusCode());
        $this->assertSame('invalid_request', $this->json($empty)['error']['code']);
        $this->assertSame('Sign-in failed', $this->json($empty)['error']['message']);

        $blankPassword = $this->request('POST', '/api/auth/password', [
            'email' => 'a@b.com',
            'password' => '',
        ]);
        $this->assertSame(400, $blankPassword->getStatusCode());

        $invalidEmail = $this->request('POST', '/api/auth/password', [
            'email' => 'not-an-email',
            'password' => 'secret',
        ]);
        $this->assertSame(401, $invalidEmail->getStatusCode());
        $this->assertSame('invalid_credentials', $this->json($invalidEmail)['error']['code']);
        $this->assertSame('Sign-in failed', $this->json($invalidEmail)['error']['message']);
        $this->assertFileDoesNotExist($this->userDbPath('not-an-email', 'password'));
        $this->assertFileDoesNotExist($this->dataDir . '/users/' . md5('not-an-email') . '.sqlite');
    }

    public function testPasswordLoginDoesNotCreateASecondFileForTheSameEmail(): void
    {
        $email = 'onefile-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email);
        $this->request('PATCH', '/api/me', ['password' => 'same-file']);
        $this->request('POST', '/api/auth/logout', []);
        $this->request('POST', '/api/auth/password', [
            'email' => $email,
            'password' => 'same-file',
        ]);
        $this->assertCount(1, glob($this->dataDir . '/users/*.sqlite') ?: []);
        $this->assertFileExists($this->userDbPath($email));
    }

    public function testRegisterEndpointCreatesAccountThenPasswordLoginWorks(): void
    {
        $email = 'reg-' . bin2hex(random_bytes(4)) . '@example.com';
        $created = $this->request('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'first-password',
            'timezone' => 'America/Chicago',
        ]);
        $this->assertSame(200, $created->getStatusCode());
        $this->assertNotSame('', $created->getHeaderLine('Set-Cookie'));
        $me = $this->json($created)['data'];
        $this->assertSame($email, $me['email']);
        $this->assertSame('America/Chicago', $me['timezone']);
        $this->assertArrayNotHasKey('password_hash', $me);
        $this->assertSame(['password'], array_column($me['identities'], 'provider'));
        $this->assertFileExists($this->userDbPath($email, 'password'));
        $this->assertStringNotContainsString('first-password', (string) $created->getBody());

        $this->request('POST', '/api/auth/logout', []);
        $login = $this->request('POST', '/api/auth/password', [
            'email' => '  ' . strtoupper($email) . '  ',
            'password' => 'first-password',
        ]);
        $this->assertSame(200, $login->getStatusCode());
        $this->assertSame($email, $this->json($login)['data']['email']);
        $this->assertCount(1, glob($this->dataDir . '/users/*.sqlite') ?: []);
    }

    public function testRegisterAcceptsUsernameInsteadOfEmail(): void
    {
        $username = 'lifter-' . bin2hex(random_bytes(3));
        $created = $this->request('POST', '/api/auth/register', [
            'username' => $username,
            'password' => 'first-password',
            'timezone' => 'UTC',
        ]);
        $this->assertSame(200, $created->getStatusCode());
        $this->assertSame($username, $this->json($created)['data']['email']);
        $this->assertFileExists($this->userDbPath($username, 'password'));

        $this->request('POST', '/api/auth/logout', []);
        $login = $this->request('POST', '/api/auth/password', [
            'username' => strtoupper($username),
            'password' => 'first-password',
        ]);
        $this->assertSame(200, $login->getStatusCode());
        $this->assertSame($username, $this->json($login)['data']['email']);
    }

    public function testRegisterRejectsDuplicateAndDoesNotCreateUnknownEmails(): void
    {
        $email = 'exists-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->assertSame(200, $this->request('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'keep-this',
        ])->getStatusCode());

        $dup = $this->request('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'other-pass',
        ]);
        $this->assertSame(409, $dup->getStatusCode());
        $this->assertSame('account_exists', $this->json($dup)['error']['code']);
        $this->assertSame('Account already exists', $this->json($dup)['error']['message']);
        $this->assertStringNotContainsString('other-pass', (string) $dup->getBody());

        $googleEmail = 'greg-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($googleEmail);
        $this->request('POST', '/api/auth/logout', []);
        $googleRegister = $this->request('POST', '/api/auth/register', [
            'email' => $googleEmail,
            'password' => 'separate-pass',
        ]);
        $this->assertSame(200, $googleRegister->getStatusCode());
        $this->assertFileExists($this->userDbPath($googleEmail));
        $this->assertFileExists($this->userDbPath($googleEmail, 'password'));
        $this->assertNotSame($this->userDbPath($googleEmail), $this->userDbPath($googleEmail, 'password'));
        $this->assertCount(3, glob($this->dataDir . '/users/*.sqlite') ?: []);

        $noJson = $this->request('POST', '/api/auth/register');
        $this->assertSame(415, $noJson->getStatusCode());

        $empty = $this->request('POST', '/api/auth/register', ['email' => '', 'password' => 'x']);
        $this->assertSame(400, $empty->getStatusCode());
        $this->assertSame('invalid_request', $this->json($empty)['error']['code']);
        $this->assertSame('Registration failed', $this->json($empty)['error']['message']);

        $blankPassword = $this->request('POST', '/api/auth/register', [
            'email' => 'a@b.com',
            'password' => '',
        ]);
        $this->assertSame(400, $blankPassword->getStatusCode());
        $this->assertSame('invalid_password', $this->json($blankPassword)['error']['code']);
        $this->assertSame('Enter a password', $this->json($blankPassword)['error']['message']);

        $invalidUsername = $this->request('POST', '/api/auth/register', [
            'email' => 'nope!',
            'password' => 'secret',
        ]);
        $this->assertSame(400, $invalidUsername->getStatusCode());
        $this->assertSame('invalid_request', $this->json($invalidUsername)['error']['code']);
        $this->assertSame([], glob($this->dataDir . '/users/' . md5('password|nope!') . '.sqlite') ?: []);
    }

    public function testRegisterWorksWhenGoogleClientIdIsEmpty(): void
    {
        $_ENV['GOOGLE_CLIENT_ID'] = '';
        putenv('GOOGLE_CLIENT_ID=');
        $this->app = require dirname(__DIR__, 2) . '/config/bootstrap.php';

        $email = 'local-' . bin2hex(random_bytes(4)) . '@example.com';
        $created = $this->request('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'local-secret',
            'timezone' => 'America/Chicago',
        ]);
        $this->assertSame(200, $created->getStatusCode());
        $payload = $this->json($created);
        $this->assertArrayNotHasKey('error', $payload);
        $this->assertSame($email, $payload['data']['email']);
        $this->assertStringNotContainsString('Google client ID', (string) $created->getBody());
        $this->assertFileExists($this->userDbPath($email, 'password'));

        $google = $this->request('POST', '/api/auth/google', ['id_token' => 'anything']);
        $this->assertSame(401, $google->getStatusCode());
        $this->assertSame('invalid_token', $this->json($google)['error']['code']);
        $this->assertSame('Google sign-in failed', $this->json($google)['error']['message']);
        $this->assertStringNotContainsString('client ID', (string) $google->getBody());
    }
}
