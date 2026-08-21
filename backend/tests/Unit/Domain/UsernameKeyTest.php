<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Domain;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Domain\UsernameKey;

#[CoversClass(UsernameKey::class)]
final class UsernameKeyTest extends TestCase
{
    public function testNormalizesTrimAndCase(): void
    {
        $key = UsernameKey::fromUsername('  Lifter.One+tag@Example.COM ');
        $this->assertSame('lifter.one+tag@example.com', $key->normalized());
    }

    #[DataProvider('invalidUsernameProvider')]
    public function testRejectsInvalidUsernames(string $username, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        UsernameKey::fromUsername($username);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidUsernameProvider(): array
    {
        return [
            'empty' => ['', 'Username cannot be empty'],
            'whitespace' => ['   ', 'Username cannot be empty'],
            'too long' => [str_repeat('a', 65), 'Username is too long'],
            'space' => ['bad name', 'Username contains invalid characters'],
            'bang' => ['nope!', 'Username contains invalid characters'],
        ];
    }
}
