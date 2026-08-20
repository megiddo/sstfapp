<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Session;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

final class SessionService
{
    private const COOKIE_MAX_AGE = 60 * 60 * 24 * 30;

    public function __construct(
        private readonly FileSessionStore $store,
        private readonly SessionCookie $cookie,
        private readonly string $secret,
    ) {
        if (strlen($this->secret) < 16) {
            throw new InvalidArgumentException('SESSION_SECRET must be at least 16 characters');
        }
    }

    public function create(string $emailHash): string
    {
        if (preg_match('/^[a-f0-9]{32}$/', $emailHash) !== 1) {
            throw new InvalidArgumentException('email_hash must be 32 lowercase hexadecimal characters');
        }

        $id = bin2hex(random_bytes(32));
        $this->store->write($id, $emailHash);

        return $this->sign($id);
    }

    public function emailHashFromCookie(?string $cookieValue): ?string
    {
        $id = $this->unsignedId($cookieValue);
        if ($id === null) {
            return null;
        }

        return $this->store->read($id);
    }

    public function destroy(?string $cookieValue): void
    {
        $id = $this->unsignedId($cookieValue);
        if ($id !== null) {
            $this->store->delete($id);
        }
    }

    public function cookieValueFrom(ServerRequestInterface $request): ?string
    {
        $params = $request->getCookieParams();
        $name = $this->cookie->name();
        if (!isset($params[$name]) || !is_string($params[$name]) || $params[$name] === '') {
            return null;
        }

        return $params[$name];
    }

    public function setCookieHeader(string $cookieValue): string
    {
        return $this->cookie->header($cookieValue, self::COOKIE_MAX_AGE);
    }

    public function expireCookieHeader(): string
    {
        return $this->cookie->expireHeader();
    }

    private function sign(string $id): string
    {
        return $id . '.' . hash_hmac('sha256', $id, $this->secret);
    }

    private function unsignedId(?string $cookieValue): ?string
    {
        if ($cookieValue === null || $cookieValue === '') {
            return null;
        }

        $parts = explode('.', $cookieValue);
        if (count($parts) !== 2) {
            return null;
        }

        [$id, $mac] = $parts;
        if (preg_match(FileSessionStore::ID_PATTERN, $id) !== 1) {
            return null;
        }
        if (!is_string($mac) || $mac === '') {
            return null;
        }

        $expected = hash_hmac('sha256', $id, $this->secret);
        if (!hash_equals($expected, $mac)) {
            return null;
        }

        return $id;
    }
}
