<?php

declare(strict_types=1);

namespace Sstf\Api\Tests\Unit\Infrastructure\Log;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Sstf\Api\Infrastructure\Log\JsonLogger;

#[CoversClass(JsonLogger::class)]
final class JsonLoggerTest extends TestCase
{
    public function testDisabledLoggerWritesNothing(): void
    {
        $wrote = [];
        $logger = new JsonLogger(false, static function (string $line) use (&$wrote): void {
            $wrote[] = $line;
        });
        $logger->info('http.request', ['email' => 'user@example.com']);
        $logger->error('boom', ['id_token' => 'secret']);
        $this->assertSame([], $wrote);
    }

    public function testInfoIsJsonLineWithoutEmailOrSecrets(): void
    {
        $wrote = [];
        $logger = new JsonLogger(true, static function (string $line) use (&$wrote): void {
            $wrote[] = $line;
        });
        $logger->info('auth.google', [
            'email' => 'User@Example.com',
            'id_token' => 'ya29.secret',
            'password' => 'hunter2',
            'path' => '/api/auth/google',
        ]);

        $this->assertCount(1, $wrote);
        $this->assertStringEndsWith("\n", $wrote[0]);
        $payload = json_decode(trim($wrote[0]), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('info', $payload['level']);
        $this->assertSame('auth.google', $payload['event']);
        $this->assertSame('/api/auth/google', $payload['path']);
        $this->assertSame('[redacted]', $payload['id_token']);
        $this->assertSame('[redacted]', $payload['password']);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertStringNotContainsString('User@Example.com', $wrote[0]);
        $this->assertStringNotContainsString('ya29.secret', $wrote[0]);
        $this->assertStringNotContainsString('hunter2', $wrote[0]);
        $this->assertSame(md5('user@example.com'), $payload['email_hash']);
    }

    public function testExistingEmailHashIsKept(): void
    {
        $wrote = [];
        $logger = new JsonLogger(true, static function (string $line) use (&$wrote): void {
            $wrote[] = $line;
        });
        $logger->info('http.request', ['email_hash' => 'abc123', 'status' => 200]);
        $payload = json_decode(trim($wrote[0]), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('abc123', $payload['email_hash']);
        $this->assertSame(200, $payload['status']);
    }

    public function testEmptyEmailIsDroppedAndNestedSecretsRedact(): void
    {
        $wrote = [];
        $logger = new JsonLogger(true, static function (string $line) use (&$wrote): void {
            $wrote[] = $line;
        });
        $logger->error('nested', [
            'user_email' => '',
            'body' => [
                'credential' => 'tok',
                'email' => 'nested@example.com',
            ],
        ]);
        $payload = json_decode(trim($wrote[0]), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('error', $payload['level']);
        $this->assertArrayNotHasKey('user_email', $payload);
        $this->assertArrayNotHasKey('email', $payload);
        $this->assertSame('[redacted]', $payload['body']['credential']);
        $this->assertSame(md5('nested@example.com'), $payload['body']['email_hash']);
        $this->assertStringNotContainsString('nested@example.com', $wrote[0]);
        $this->assertStringNotContainsString('tok', $wrote[0]);
    }

    public function testStderrFactoryReturnsLogger(): void
    {
        $disabled = JsonLogger::stderr(false);
        $disabled->info('noop');
        $this->assertInstanceOf(JsonLogger::class, $disabled);

        $enabled = JsonLogger::stderr(true);
        $enabled->info('http.request', ['path' => '/api/health', 'status' => 200]);
        $this->assertInstanceOf(JsonLogger::class, $enabled);
    }
}
