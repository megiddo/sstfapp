<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\EmailKey;

#[CoversClass(EmailKey::class)]
final class EmailKeyTest extends TestCase
{
    public function testNormalizesAndHashesWithMd5Hex(): void
    {
        $key = EmailKey::fromEmail('  User.Name+tag@Example.COM ');

        $this->assertSame('user.name+tag@example.com', $key->normalized());
        $this->assertSame(md5('user.name+tag@example.com'), $key->hash());
        $this->assertSame(32, strlen($key->hash()));
        $this->assertSame(1, preg_match('/^[a-f0-9]{32}$/', $key->hash()));
        $this->assertNotSame(md5('  User.Name+tag@Example.COM '), $key->hash());
        $this->assertNotSame(sha1('user.name+tag@example.com'), $key->hash());
        $this->assertSame(strtolower($key->hash()), $key->hash());
    }

    public function testDoesNotFoldGmailDots(): void
    {
        $dotted = EmailKey::fromEmail('n.smith@gmail.com');
        $plain = EmailKey::fromEmail('nsmith@gmail.com');

        $this->assertNotSame($dotted->hash(), $plain->hash());
        $this->assertNotSame($dotted->normalized(), $plain->normalized());
    }

    public function testSameNormalizedEmailSameHash(): void
    {
        $a = EmailKey::fromEmail('A@B.COM');
        $b = EmailKey::fromEmail('a@b.com');

        $this->assertSame($a->normalized(), $b->normalized());
        $this->assertSame($a->hash(), $b->hash());
    }

    #[DataProvider('invalidEmailProvider')]
    public function testRejectsInvalidEmails(string $email, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        EmailKey::fromEmail($email);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidEmailProvider(): array
    {
        return [
            'empty' => ['', 'Email cannot be empty'],
            'whitespace' => ['   ', 'Email cannot be empty'],
            'no at' => ['not-an-email', 'Email must contain @'],
        ];
    }
}
