<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\Session;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Infrastructure\Session\FileSessionStore;

#[CoversClass(FileSessionStore::class)]
final class FileSessionStoreTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/sstf-sess-' . getmypid() . '-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->tmp);
        parent::tearDown();
    }

    public function testWriteReadDeleteRoundTrip(): void
    {
        $store = new FileSessionStore($this->tmp);
        $id = str_repeat('ab', 32);
        $hash = '0123456789abcdef0123456789abcdef';

        $this->assertNull($store->read($id));
        $store->write($id, $hash);
        $path = $this->tmp . '/' . $id;
        $this->assertFileExists($path);
        $this->assertSame(FileSessionStore::FILE_MODE, fileperms($path) & 0777);
        $this->assertSame($hash, $store->read($id));
        $this->assertStringNotContainsString('@', (string) file_get_contents($path));

        $store->delete($id);
        $this->assertFileDoesNotExist($path);
        $this->assertNull($store->read($id));
        $store->delete($id);
    }

    public function testReadRejectsCorruptPayloads(): void
    {
        $store = new FileSessionStore($this->tmp);
        $id = str_repeat('cd', 32);
        mkdir($this->tmp, 0700, true);

        file_put_contents($this->tmp . '/' . $id, '');
        $this->assertNull($store->read($id));

        $id2 = str_repeat('ef', 32);
        file_put_contents($this->tmp . '/' . $id2, 'not-json');
        $this->assertNull($store->read($id2));

        $id3 = str_repeat('11', 32);
        file_put_contents($this->tmp . '/' . $id3, '{"email_hash":"not-a-hash"}');
        $this->assertNull($store->read($id3));

        $id4 = str_repeat('22', 32);
        file_put_contents($this->tmp . '/' . $id4, '[]');
        $this->assertNull($store->read($id4));
    }

    public function testRejectsInvalidIdsAndHashes(): void
    {
        $store = new FileSessionStore($this->tmp);
        $hash = '0123456789abcdef0123456789abcdef';

        try {
            $store->write('../' . str_repeat('a', 64), $hash);
            $this->fail('path traversal id');
        } catch (InvalidArgumentException) {
        }

        try {
            $store->write(str_repeat('a', 64), 'not-hash');
            $this->fail('bad hash');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        $store->read('short');
    }

    public function testEmptyDirectoryRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FileSessionStore('');
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
