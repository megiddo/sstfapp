<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Log;

final class JsonLogger
{
    /** @var list<string> */
    private const SECRET_NEEDLES = ['password', 'token', 'credential', 'authorization', 'cookie', 'secret'];

    /**
     * @param callable(string): void $write
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly mixed $write,
    ) {
    }

    public static function stderr(bool $enabled): self
    {
        return new self($enabled, static function (string $line): void {
            file_put_contents('php://stderr', $line, FILE_APPEND);
        });
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $event, array $context = []): void
    {
        $this->write('info', $event, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $event, array $context = []): void
    {
        $this->write('error', $event, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $level, string $event, array $context): void
    {
        if ($this->enabled !== true) {
            return;
        }

        $payload = [
            'ts' => gmdate('c'),
            'level' => $level,
            'event' => $event,
        ];
        foreach ($this->redact($context) as $key => $value) {
            $payload[$key] = $value;
        }

        ($this->write)((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function redact(array $context): array
    {
        $out = [];
        foreach ($context as $key => $value) {
            $name = (string) $key;
            $lower = strtolower($name);
            if (is_array($value)) {
                $out[$name] = $this->redact($this->stringKeyed($value));
                continue;
            }
            if ($this->isSecretKey($lower)) {
                $out[$name] = '[redacted]';
                continue;
            }
            if ($this->isEmailKey($lower)) {
                if (is_string($value) && $value !== '') {
                    $out['email_hash'] = md5(strtolower(trim($value)));
                }
                continue;
            }
            $out[$name] = $value;
        }

        return $out;
    }

    private function isSecretKey(string $lower): bool
    {
        foreach (self::SECRET_NEEDLES as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isEmailKey(string $lower): bool
    {
        if ($lower === 'email_hash') {
            return false;
        }

        return $lower === 'email' || str_contains($lower, 'email');
    }

    /**
     * @param array<mixed> $value
     * @return array<string, mixed>
     */
    private function stringKeyed(array $value): array
    {
        $out = [];
        foreach ($value as $key => $item) {
            $out[(string) $key] = $item;
        }

        return $out;
    }
}
