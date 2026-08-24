<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Google;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Sstf\Api\Domain\ClockInterface;
use Sstf\Api\Infrastructure\Session\SessionCookie;

final class OAuthStateService
{
    public const COOKIE = 'sstf_oauth';

    private const TTL_SECONDS = 600;

    public function __construct(
        private readonly SessionCookie $cookie,
        private readonly string $secret,
        private readonly ClockInterface $clock,
        private readonly int $ttlSeconds = self::TTL_SECONDS,
    ) {
        if (strlen($this->secret) < 16) {
            throw new InvalidArgumentException('SESSION_SECRET must be at least 16 characters');
        }
        if ($this->ttlSeconds < 1) {
            throw new InvalidArgumentException('OAuth state TTL must be at least 1 second');
        }
    }

    /**
     * @return array{state: string, header: string}
     */
    public function issue(?string $timezone): array
    {
        $state = bin2hex(random_bytes(16));
        $tz = is_string($timezone) ? $timezone : '';
        $expires = $this->clock->now()->getTimestamp() + $this->ttlSeconds;
        $payload = $state . '.' . $expires . '.' . rawurlencode($tz);
        $value = $payload . '.' . hash_hmac('sha256', $payload, $this->secret);

        return [
            'state' => $state,
            'header' => $this->cookie->header($value, $this->ttlSeconds),
        ];
    }

    /**
     * @return array{state: string, timezone: ?string}|null
     */
    public function read(ServerRequestInterface $request): ?array
    {
        $params = $request->getCookieParams();
        if (!isset($params[self::COOKIE]) || !is_string($params[self::COOKIE]) || $params[self::COOKIE] === '') {
            return null;
        }

        $parts = explode('.', $params[self::COOKIE], 4);
        if (count($parts) !== 4) {
            return null;
        }

        [$state, $expiresRaw, $tzEnc, $hmac] = $parts;
        if ($state === '' || $hmac === '' || !ctype_digit($expiresRaw)) {
            return null;
        }

        // PHP urldecodes cookie values, so America%2FChicago arrives as America/Chicago.
        // HMAC is over the percent-encoded timezone; re-encode before compare.
        $timezone = rawurldecode($tzEnc);
        $payload = $state . '.' . $expiresRaw . '.' . rawurlencode($timezone);
        $expected = hash_hmac('sha256', $payload, $this->secret);
        if (!hash_equals($expected, $hmac)) {
            return null;
        }

        if ((int) $expiresRaw <= $this->clock->now()->getTimestamp()) {
            return null;
        }

        if ($timezone === '') {
            $timezone = null;
        }

        return [
            'state' => $state,
            'timezone' => $timezone,
        ];
    }

    public function expireHeader(): string
    {
        return $this->cookie->expireHeader();
    }
}
