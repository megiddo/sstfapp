<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Http;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Http\RedirectResponder;

#[CoversClass(RedirectResponder::class)]
final class RedirectResponderTest extends TestCase
{
    public function testToSetsLocation(): void
    {
        $response = RedirectResponder::to('/login?error=google');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login?error=google', $response->getHeaderLine('Location'));
    }

    public function testEmptyLocationIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RedirectResponder::to('');
    }
}
