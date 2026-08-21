<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use Sstf\Api\Domain\IanaTimezone;
use Sstf\Api\Domain\InvalidTimezoneException;
use Sstf\Api\Domain\InvalidWeightUnitException;
use Sstf\Api\Http\Controllers\MeController;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;
use Sstf\Api\Services\AuthService;
use Sstf\Api\Tests\HttpTestCase;

#[CoversClass(MeController::class)]
#[CoversClass(AuthService::class)]
#[CoversClass(UserDirectory::class)]
#[CoversClass(IanaTimezone::class)]
#[CoversClass(InvalidTimezoneException::class)]
#[CoversClass(InvalidWeightUnitException::class)]
#[CoversClass(JsonResponder::class)]
final class MePatchTest extends HttpTestCase
{
    public function testPatchUpdatesTimezoneAndWeightUnit(): void
    {
        $email = 'patch-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email, 'America/Chicago');

        $me = $this->json($this->request('GET', '/api/me'));
        $this->assertSame('America/Chicago', $me['data']['timezone']);
        $this->assertSame('lb', $me['data']['weight_unit']);

        $tz = $this->request('PATCH', '/api/me', ['timezone' => '  Europe/London  ']);
        $this->assertSame(200, $tz->getStatusCode());
        $this->assertSame('Europe/London', $this->json($tz)['data']['timezone']);
        $this->assertSame('lb', $this->json($tz)['data']['weight_unit']);
        $this->assertSame($email, $this->json($tz)['data']['email']);

        $unit = $this->request('PATCH', '/api/me', ['weight_unit' => 'kg']);
        $this->assertSame(200, $unit->getStatusCode());
        $this->assertSame('kg', $this->json($unit)['data']['weight_unit']);
        $this->assertSame('Europe/London', $this->json($unit)['data']['timezone']);

        $both = $this->request('PATCH', '/api/me', [
            'timezone' => 'America/New_York',
            'weight_unit' => 'lb',
        ]);
        $this->assertSame(200, $both->getStatusCode());
        $this->assertSame('America/New_York', $this->json($both)['data']['timezone']);
        $this->assertSame('lb', $this->json($both)['data']['weight_unit']);

        $noop = $this->request('PATCH', '/api/me', []);
        $this->assertSame(200, $noop->getStatusCode());
        $this->assertSame('America/New_York', $this->json($noop)['data']['timezone']);
        $this->assertSame('lb', $this->json($noop)['data']['weight_unit']);

        $again = $this->json($this->request('GET', '/api/me'));
        $this->assertSame('America/New_York', $again['data']['timezone']);
        $this->assertSame('lb', $again['data']['weight_unit']);
        $this->assertSame([['provider' => 'google']], $again['data']['identities']);
    }

    public function testPatchRejectsInvalidTimezoneAndLeavesAccountUnchanged(): void
    {
        $email = 'badtz-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email, 'America/Chicago');

        $response = $this->request('PATCH', '/api/me', ['timezone' => 'Not/A_Zone']);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('invalid_timezone', $this->json($response)['error']['code']);
        $this->assertArrayNotHasKey('data', $this->json($response));

        $empty = $this->request('PATCH', '/api/me', ['timezone' => '']);
        $this->assertSame(400, $empty->getStatusCode());
        $this->assertSame('invalid_timezone', $this->json($empty)['error']['code']);

        $nullTz = $this->request('PATCH', '/api/me', ['timezone' => null]);
        $this->assertSame(400, $nullTz->getStatusCode());
        $this->assertSame('invalid_timezone', $this->json($nullTz)['error']['code']);

        $me = $this->json($this->request('GET', '/api/me'));
        $this->assertSame('America/Chicago', $me['data']['timezone']);
        $this->assertSame('lb', $me['data']['weight_unit']);
        $this->assertNotSame('UTC', $me['data']['timezone']);
    }

    public function testPatchRejectsInvalidWeightUnit(): void
    {
        $email = 'badunit-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email);

        foreach (['st', 'LB', 'KG', 'pounds', ''] as $unit) {
            $response = $this->request('PATCH', '/api/me', ['weight_unit' => $unit]);
            $this->assertSame(400, $response->getStatusCode(), $unit);
            $this->assertSame('invalid_weight_unit', $this->json($response)['error']['code']);
        }

        $me = $this->json($this->request('GET', '/api/me'));
        $this->assertSame('lb', $me['data']['weight_unit']);
        $this->assertNotSame('kg', $me['data']['weight_unit']);
    }

    public function testPatchWithoutSessionIs401(): void
    {
        $response = $this->request('PATCH', '/api/me', ['timezone' => 'UTC']);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('unauthenticated', $this->json($response)['error']['code']);
    }

    public function testPatchSetsPasswordWithoutReturningTheHash(): void
    {
        $email = 'setpw-' . bin2hex(random_bytes(4)) . '@example.com';
        $this->signIn($email);

        $response = $this->request('PATCH', '/api/me', ['password' => 'gym-secret']);
        $this->assertSame(200, $response->getStatusCode());
        $payload = $this->json($response);
        $this->assertArrayNotHasKey('password_hash', $payload['data']);
        $providers = array_column($payload['data']['identities'], 'provider');
        $this->assertContains('password', $providers);
        $this->assertStringNotContainsString('gym-secret', (string) $response->getBody());

        $badType = $this->request('PATCH', '/api/me', ['password' => 12]);
        $this->assertSame(400, $badType->getStatusCode());
        $this->assertSame('invalid_password', $this->json($badType)['error']['code']);

        $badCurrentType = $this->request('PATCH', '/api/me', [
            'password' => 'other',
            'current_password' => false,
        ]);
        $this->assertSame(400, $badCurrentType->getStatusCode());
        $this->assertSame('invalid_current_password', $this->json($badCurrentType)['error']['code']);
    }
}
