<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\EmailKey;
use Sstf\Api\Domain\RepoKey;
use Sstf\Api\Domain\UsernameKey;

#[CoversClass(RepoKey::class)]
#[CoversClass(EmailKey::class)]
#[CoversClass(UsernameKey::class)]
final class RepoKeyTest extends TestCase
{
    public function testGoogleAndPasswordHashesAreNamespaced(): void
    {
        $google = RepoKey::google('  Ada@Example.COM ');
        $password = RepoKey::password('  Ada@Example.COM ');

        $this->assertSame('ada@example.com', $google->normalized());
        $this->assertSame('ada@example.com', $password->normalized());
        $this->assertSame(md5('google|ada@example.com'), $google->hash());
        $this->assertSame(md5('password|ada@example.com'), $password->hash());
        $this->assertNotSame($google->hash(), $password->hash());
        $this->assertNotSame(md5('ada@example.com'), $google->hash());
        $this->assertSame(32, strlen($google->hash()));
        $this->assertSame(1, preg_match('/^[a-f0-9]{32}$/', $google->hash()));
    }

    public function testPasswordUsernameDoesNotRequireAnEmail(): void
    {
        $key = RepoKey::password('Lifter.One');
        $this->assertSame('lifter.one', $key->normalized());
        $this->assertSame(md5('password|lifter.one'), $key->hash());
    }
}
