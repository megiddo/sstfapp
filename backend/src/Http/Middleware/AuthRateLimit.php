<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Infrastructure\RateLimit\AuthRateLimiterInterface;
use Throwable;

final class AuthRateLimit implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthRateLimiterInterface $limiter,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (!str_starts_with($path, '/api/auth/')) {
            return $handler->handle($request);
        }

        try {
            $allowed = $this->limiter->allow($this->clientKey($request));
        } catch (Throwable) {
            $allowed = false;
        }

        if ($allowed !== true) {
            return JsonResponder::error('rate_limited', 'Too many attempts', 429);
        }

        return $handler->handle($request);
    }

    private function clientKey(ServerRequestInterface $request): string
    {
        $params = $request->getServerParams();
        $ip = $params['REMOTE_ADDR'] ?? '';
        if (!is_string($ip) || $ip === '') {
            return 'unknown';
        }

        return $ip;
    }
}
