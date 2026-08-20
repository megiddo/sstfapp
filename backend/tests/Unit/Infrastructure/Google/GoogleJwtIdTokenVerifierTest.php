<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\Google;

use InvalidArgumentException;
use OpenSSLAsymmetricKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Infrastructure\Google\GoogleCertsProviderInterface;
use Sstf\Api\Infrastructure\Google\GoogleJwtIdTokenVerifier;
use Sstf\Api\Infrastructure\Google\UrlGoogleCertsProvider;
use Sstf\Api\Infrastructure\Http\UrlFetcher;
use Sstf\Api\Tests\Fakes\FakeClock;

#[CoversClass(GoogleJwtIdTokenVerifier::class)]
#[CoversClass(UrlGoogleCertsProvider::class)]
#[CoversClass(UrlFetcher::class)]
final class GoogleJwtIdTokenVerifierTest extends TestCase
{
    private const AUDIENCE = 'test-google-client-id.apps.googleusercontent.com';
    private const KID = 'test-kid';

    private OpenSSLAsymmetricKey $privateKey;

    private string $pem;

    private FakeClock $clock;

    protected function setUp(): void
    {
        parent::setUp();
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
        $csr = openssl_csr_new(['commonName' => 'sstf-test'], $key, ['digest_alg' => 'sha256']);
        $this->assertNotFalse($csr);
        $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        $this->assertNotFalse($cert);
        openssl_x509_export($cert, $pem);
        $this->assertIsString($pem);
        $this->privateKey = $key;
        $this->pem = $pem;
        $this->clock = new FakeClock(1_700_000_000);
    }

    public function testVerifiesValidToken(): void
    {
        $token = $this->mint($this->payload());
        $user = $this->verifier()->verify($token);

        $this->assertSame('user@example.com', $user->email);
        $this->assertTrue($user->emailVerified);
        $this->assertSame('google-sub', $user->subject);
        $this->assertSame(self::AUDIENCE, $user->audience);
        $this->assertSame('https://accounts.google.com', $user->issuer);
        $this->assertSame(1_700_000_000 + 60, $user->expiresAt);
    }

    public function testAcceptsAccountsGoogleComIssuer(): void
    {
        $token = $this->mint($this->payload(['iss' => 'accounts.google.com']));
        $user = $this->verifier()->verify($token);
        $this->assertSame('accounts.google.com', $user->issuer);
        $this->assertTrue($user->emailVerified);
    }

    public function testEmailVerifiedFalseDoesNotThrow(): void
    {
        $token = $this->mint($this->payload(['email_verified' => false]));
        $user = $this->verifier()->verify($token);
        $this->assertFalse($user->emailVerified);
    }

    public function testStringEmailVerifiedIsNotTrue(): void
    {
        $token = $this->mint($this->payload(['email_verified' => 'true']));
        $user = $this->verifier()->verify($token);
        $this->assertFalse($user->emailVerified);
    }

    public function testRejectsWrongAudience(): void
    {
        $token = $this->mint($this->payload(['aud' => 'other-client.apps.googleusercontent.com']));
        $this->expectException(InvalidGoogleIdTokenException::class);
        $this->verifier()->verify($token);
    }

    public function testRejectsExpiredAndEqualNow(): void
    {
        $expired = $this->mint($this->payload(['exp' => 1_700_000_000 - 1]));
        try {
            $this->verifier()->verify($expired);
            $this->fail('expired');
        } catch (InvalidGoogleIdTokenException) {
        }

        $equal = $this->mint($this->payload(['exp' => 1_700_000_000]));
        $this->expectException(InvalidGoogleIdTokenException::class);
        $this->verifier()->verify($equal);
    }

    public function testAcceptsExpJustAfterNow(): void
    {
        $token = $this->mint($this->payload(['exp' => 1_700_000_001]));
        $user = $this->verifier()->verify($token);
        $this->assertSame(1_700_000_001, $user->expiresAt);
    }

    public function testRejectsBadIssuerAlgKidAndSignature(): void
    {
        $badIss = $this->mint($this->payload(['iss' => 'https://evil.example']));
        try {
            $this->verifier()->verify($badIss);
            $this->fail('iss');
        } catch (InvalidGoogleIdTokenException) {
        }

        $hs = $this->mint($this->payload(), ['alg' => 'HS256', 'kid' => self::KID]);
        try {
            $this->verifier()->verify($hs);
            $this->fail('alg');
        } catch (InvalidGoogleIdTokenException) {
        }

        $noKid = $this->mint($this->payload(), ['alg' => 'RS256']);
        try {
            $this->verifier()->verify($noKid);
            $this->fail('kid');
        } catch (InvalidGoogleIdTokenException) {
        }

        $unknownKid = $this->mint($this->payload(), ['alg' => 'RS256', 'kid' => 'nope']);
        try {
            $this->verifier()->verify($unknownKid);
            $this->fail('unknown kid');
        } catch (InvalidGoogleIdTokenException) {
        }

        $other = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $other);
        $badSig = $this->mint($this->payload(), ['alg' => 'RS256', 'kid' => self::KID], $other);
        $this->expectException(InvalidGoogleIdTokenException::class);
        $this->verifier()->verify($badSig);
    }

    public function testRejectsMalformedAndMissingClaims(): void
    {
        $verifier = $this->verifier();
        foreach (['', 'a', 'a.b', 'a.b.c.d', '..'] as $token) {
            try {
                $verifier->verify($token);
                $this->fail('should reject: ' . $token);
            } catch (InvalidGoogleIdTokenException) {
            }
        }

        foreach (
            [
                ['email' => ''],
                ['email' => '   '],
                ['sub' => ''],
            ] as $override
        ) {
            $token = $this->mint($this->payload($override));
            try {
                $verifier->verify($token);
                $this->fail('claim');
            } catch (InvalidGoogleIdTokenException) {
            }
        }

        $payload = $this->payload();
        unset($payload['email']);
        try {
            $verifier->verify($this->mint($payload));
            $this->fail('missing email');
        } catch (InvalidGoogleIdTokenException) {
        }

        $payload = $this->payload();
        unset($payload['sub']);
        try {
            $verifier->verify($this->mint($payload));
            $this->fail('missing sub');
        } catch (InvalidGoogleIdTokenException) {
        }

        $payload = $this->payload();
        unset($payload['exp']);
        $this->expectException(InvalidGoogleIdTokenException::class);
        $verifier->verify($this->mint($payload));
    }

    public function testEmptyAudienceRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GoogleJwtIdTokenVerifier('', $this->certs(), $this->clock);
    }

    public function testUrlCertsProviderParsesPemMap(): void
    {
        $tmp = sys_get_temp_dir() . '/sstf-certs-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($tmp, json_encode([self::KID => $this->pem], JSON_THROW_ON_ERROR));
        $provider = new UrlGoogleCertsProvider($tmp, new UrlFetcher());
        $keys = $provider->publicKeys();
        $this->assertSame([self::KID => $this->pem], $keys);
        unlink($tmp);
    }

    public function testUrlCertsProviderRejectsInvalidJsonAndEmptyUrl(): void
    {
        $tmp = sys_get_temp_dir() . '/sstf-certs-bad-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($tmp, '{');
        $provider = new UrlGoogleCertsProvider($tmp, new UrlFetcher());
        try {
            $provider->publicKeys();
            $this->fail('invalid json');
        } catch (RuntimeException) {
        }
        unlink($tmp);

        try {
            new UrlGoogleCertsProvider('', new UrlFetcher());
            $this->fail('empty url');
        } catch (InvalidArgumentException) {
        }

        $empty = sys_get_temp_dir() . '/sstf-certs-empty-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($empty, '[]');
        try {
            (new UrlGoogleCertsProvider($empty, new UrlFetcher()))->publicKeys();
            $this->fail('empty array');
        } catch (RuntimeException) {
        }
        unlink($empty);

        $badEntry = sys_get_temp_dir() . '/sstf-certs-entry-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($badEntry, json_encode(['kid' => ''], JSON_THROW_ON_ERROR));
        try {
            (new UrlGoogleCertsProvider($badEntry, new UrlFetcher()))->publicKeys();
            $this->fail('empty pem');
        } catch (RuntimeException) {
        }
        unlink($badEntry);

        $emptyObject = sys_get_temp_dir() . '/sstf-certs-obj-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($emptyObject, '{}');
        try {
            (new UrlGoogleCertsProvider($emptyObject, new UrlFetcher()))->publicKeys();
            $this->fail('empty object');
        } catch (RuntimeException) {
        }
        unlink($emptyObject);
    }

    public function testUrlFetcher(): void
    {
        $fetcher = new UrlFetcher();
        try {
            $fetcher->get('');
            $this->fail('empty');
        } catch (InvalidArgumentException) {
        }

        $path = sys_get_temp_dir() . '/sstf-fetch-' . bin2hex(random_bytes(4)) . '.txt';
        file_put_contents($path, 'hello');
        $this->assertSame('hello', $fetcher->get($path));
        unlink($path);

        $this->expectException(RuntimeException::class);
        $fetcher->get($path);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $header
     */
    private function mint(array $payload, array $header = ['alg' => 'RS256', 'kid' => self::KID], ?OpenSSLAsymmetricKey $key = null): string
    {
        $h = $this->b64url((string) json_encode($header, JSON_THROW_ON_ERROR));
        $p = $this->b64url((string) json_encode($payload, JSON_THROW_ON_ERROR));
        openssl_sign($h . '.' . $p, $sig, $key ?? $this->privateKey, OPENSSL_ALGO_SHA256);
        $this->assertIsString($sig);

        return $h . '.' . $p . '.' . $this->b64url($sig);
    }

    /**
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => self::AUDIENCE,
            'exp' => 1_700_000_000 + 60,
            'email' => 'user@example.com',
            'email_verified' => true,
            'sub' => 'google-sub',
        ], $override);
    }

    private function verifier(): GoogleJwtIdTokenVerifier
    {
        return new GoogleJwtIdTokenVerifier(self::AUDIENCE, $this->certs(), $this->clock);
    }

    private function certs(): GoogleCertsProviderInterface
    {
        return new class ($this->pem) implements GoogleCertsProviderInterface {
            public function __construct(private readonly string $pem)
            {
            }

            public function publicKeys(): array
            {
                return ['test-kid' => $this->pem];
            }
        };
    }

    private function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
