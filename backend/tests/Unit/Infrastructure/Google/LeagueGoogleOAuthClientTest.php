<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\Google;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sstf\Api\Domain\InvalidGoogleIdTokenException;
use Sstf\Api\Infrastructure\Google\LeagueGoogleOAuthClient;

#[CoversClass(LeagueGoogleOAuthClient::class)]
final class LeagueGoogleOAuthClientTest extends TestCase
{
    public function testAuthorizationUrlAndFetchUser(): void
    {
        $provider = $this->createMock(AbstractProvider::class);
        $provider->method('getAuthorizationUrl')->willReturnCallback(
            static function (array $options): string {
                TestCase::assertSame('abc', $options['state'] ?? null);

                return 'https://accounts.google.com/o/oauth2/v2/auth?state=abc';
            },
        );
        $token = $this->createMock(AccessToken::class);
        $token->method('getExpires')->willReturn(1_704_067_200);
        $owner = $this->createMock(ResourceOwnerInterface::class);
        $owner->method('getId')->willReturn('sub-1');
        $owner->method('toArray')->willReturn([
            'email' => 'user@example.com',
            'email_verified' => true,
            'sub' => 'sub-1',
        ]);
        $provider->method('getAccessToken')->willReturn($token);
        $provider->method('getResourceOwner')->willReturn($owner);

        $client = new LeagueGoogleOAuthClient($provider, 'client-id.apps.googleusercontent.com');
        $this->assertTrue($client->isConfigured());
        $this->assertSame(
            'https://accounts.google.com/o/oauth2/v2/auth?state=abc',
            $client->authorizationUrl('abc'),
        );

        $user = $client->fetchUser('auth-code');
        $this->assertSame('user@example.com', $user->email);
        $this->assertTrue($user->emailVerified);
        $this->assertSame('sub-1', $user->subject);
        $this->assertSame('client-id.apps.googleusercontent.com', $user->audience);
        $this->assertSame('https://accounts.google.com', $user->issuer);
        $this->assertSame(1_704_067_200, $user->expiresAt);
    }

    public function testFetchUserMapsVerifiedFlagsAndFallbackSubject(): void
    {
        $provider = $this->createMock(AbstractProvider::class);
        $token = $this->createMock(AccessToken::class);
        $token->method('getExpires')->willReturn(null);
        $owner = $this->createMock(ResourceOwnerInterface::class);
        $owner->method('getId')->willReturn(null);
        $owner->method('toArray')->willReturn([
            'email' => 'alt@example.com',
            'email_verified' => 'true',
            'id' => 'gid-9',
        ]);
        $provider->method('getAccessToken')->willReturn($token);
        $provider->method('getResourceOwner')->willReturn($owner);

        $before = time();
        $user = (new LeagueGoogleOAuthClient($provider, 'cid'))->fetchUser('code');
        $this->assertTrue($user->emailVerified);
        $this->assertSame('gid-9', $user->subject);
        $this->assertGreaterThanOrEqual($before + 3600, $user->expiresAt);

        $ownerInt = $this->createMock(ResourceOwnerInterface::class);
        $ownerInt->method('getId')->willReturn('');
        $ownerInt->method('toArray')->willReturn([
            'email' => 'n@example.com',
            'email_verified' => 1,
            'sub' => 'from-sub',
        ]);
        $providerInt = $this->createMock(AbstractProvider::class);
        $providerInt->method('getAccessToken')->willReturn($token);
        $providerInt->method('getResourceOwner')->willReturn($ownerInt);
        $verified = (new LeagueGoogleOAuthClient($providerInt, 'cid'))->fetchUser('code');
        $this->assertTrue($verified->emailVerified);
        $this->assertSame('from-sub', $verified->subject);
    }

    public function testRejectsEmptyInputsMissingProfileAndProviderErrors(): void
    {
        $provider = $this->createMock(AbstractProvider::class);
        $empty = new LeagueGoogleOAuthClient($provider, '');
        $this->assertFalse($empty->isConfigured());
        $this->expectException(InvalidGoogleIdTokenException::class);
        $empty->authorizationUrl('state');
    }

    public function testEmptyStateAndCodeAreRejected(): void
    {
        $provider = $this->createMock(AbstractProvider::class);
        $client = new LeagueGoogleOAuthClient($provider, 'cid');
        try {
            $client->authorizationUrl('');
            $this->fail('state');
        } catch (InvalidGoogleIdTokenException) {
        }
        $this->expectException(InvalidGoogleIdTokenException::class);
        $client->fetchUser('');
    }

    public function testFetchUserRejectsMissingEmailSubjectAndThrownErrors(): void
    {
        $token = $this->createMock(AccessToken::class);
        $token->method('getExpires')->willReturn(null);

        $noEmail = $this->createMock(ResourceOwnerInterface::class);
        $noEmail->method('getId')->willReturn('sub');
        $noEmail->method('toArray')->willReturn(['email' => '  ']);
        $provider = $this->createMock(AbstractProvider::class);
        $provider->method('getAccessToken')->willReturn($token);
        $provider->method('getResourceOwner')->willReturn($noEmail);
        try {
            (new LeagueGoogleOAuthClient($provider, 'cid'))->fetchUser('code');
            $this->fail('email');
        } catch (InvalidGoogleIdTokenException) {
        }

        $noSubject = $this->createMock(ResourceOwnerInterface::class);
        $noSubject->method('getId')->willReturn(null);
        $noSubject->method('toArray')->willReturn(['email' => 'a@b.com']);
        $provider2 = $this->createMock(AbstractProvider::class);
        $provider2->method('getAccessToken')->willReturn($token);
        $provider2->method('getResourceOwner')->willReturn($noSubject);
        try {
            (new LeagueGoogleOAuthClient($provider2, 'cid'))->fetchUser('code');
            $this->fail('subject');
        } catch (InvalidGoogleIdTokenException) {
        }

        $throws = $this->createMock(AbstractProvider::class);
        $throws->method('getAccessToken')->willThrowException(
            new IdentityProviderException('nope', 0, ''),
        );
        try {
            (new LeagueGoogleOAuthClient($throws, 'cid'))->fetchUser('code');
            $this->fail('identity');
        } catch (InvalidGoogleIdTokenException) {
        }

        $boom = $this->createMock(AbstractProvider::class);
        $boom->method('getAccessToken')->willThrowException(new RuntimeException('offline'));
        $this->expectException(InvalidGoogleIdTokenException::class);
        (new LeagueGoogleOAuthClient($boom, 'cid'))->fetchUser('code');
    }

    public function testUnconfiguredFetchUserIsRejected(): void
    {
        $this->expectException(InvalidGoogleIdTokenException::class);
        (new LeagueGoogleOAuthClient($this->createMock(AbstractProvider::class), ''))->fetchUser('code');
    }
}
