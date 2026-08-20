<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Infrastructure\Session\SessionService;

final class SessionAuth implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionService $sessions,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($this->isPublic($path)) {
            return $handler->handle($request);
        }

        $cookie = $this->sessions->cookieValueFrom($request);
        $hash = $this->sessions->emailHashFromCookie($cookie);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        return $handler->handle($request->withAttribute('email_hash', $hash));
    }

    private function isPublic(string $path): bool
    {
        if ($path === '/api/health') {
            return true;
        }
        if (str_starts_with($path, '/api/auth/')) {
            return true;
        }
        if (!str_starts_with($path, '/api/')) {
            return true;
        }

        return false;
    }
}
