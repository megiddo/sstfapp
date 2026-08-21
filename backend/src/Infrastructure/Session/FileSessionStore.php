<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Session;

use InvalidArgumentException;

final class FileSessionStore
{
    public const FILE_MODE = 0600;
    public const DIR_MODE = 0700;
    public const ID_PATTERN = '/^[a-f0-9]{64}$/';

    public function __construct(
        private readonly string $directory,
    ) {
        if ($this->directory === '') {
            throw new InvalidArgumentException('Session directory cannot be empty');
        }
    }

    public function write(string $sessionId, string $emailHash): void
    {
        $this->assertId($sessionId);
        $this->assertEmailHash($emailHash);
        $this->ensureDirectory();

        $path = $this->pathFor($sessionId);
        $payload = json_encode(['email_hash' => $emailHash], JSON_THROW_ON_ERROR);
        file_put_contents($path, $payload);
        chmod($path, self::FILE_MODE);
    }

    public function read(string $sessionId): ?string
    {
        $this->assertId($sessionId);
        $path = $this->pathFor($sessionId);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $hash = $decoded['email_hash'] ?? null;
        if (!is_string($hash) || preg_match('/^[a-f0-9]{32}$/', $hash) !== 1) {
            return null;
        }

        return $hash;
    }

    public function delete(string $sessionId): void
    {
        $this->assertId($sessionId);
        $path = $this->pathFor($sessionId);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function assertId(string $sessionId): void
    {
        if (preg_match(self::ID_PATTERN, $sessionId) !== 1) {
            throw new InvalidArgumentException('Session id must be 64 lowercase hexadecimal characters');
        }
    }

    private function assertEmailHash(string $emailHash): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $emailHash) !== 1) {
            throw new InvalidArgumentException('email_hash must be 32 lowercase hexadecimal characters');
        }
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, self::DIR_MODE, true) && !is_dir($this->directory)) {
            throw new InvalidArgumentException('Unable to create session directory: ' . $this->directory);
        }
    }

    private function pathFor(string $sessionId): string
    {
        return $this->directory . '/' . $sessionId;
    }
}
