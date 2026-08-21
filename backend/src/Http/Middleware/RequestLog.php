<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sstf\Api\Infrastructure\Log\JsonLogger;
use Throwable;

final class RequestLog implements MiddlewareInterface
{
    public const HEADER = 'X-Request-Id';

    public function __construct(
        private readonly JsonLogger $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $id = $this->requestId($request);
        $request = $request->withAttribute('request_id', $id);
        $response = $handler->handle($request);

        $context = [
            'request_id' => $id,
            'method' => $request->getMethod(),
            'path' => $request->getUri()->getPath(),
            'status' => $response->getStatusCode(),
        ];
        $hash = $request->getAttribute('email_hash');
        if (is_string($hash) && $hash !== '') {
            $context['email_hash'] = $hash;
        }

        try {
            $this->logger->info('http.request', $context);
        } catch (Throwable) {
            // Logging must not fail the request.
        }

        return $response->withHeader(self::HEADER, $id);
    }

    private function requestId(ServerRequestInterface $request): string
    {
        $incoming = $request->getHeaderLine(self::HEADER);
        if ($this->isValidId($incoming)) {
            return $incoming;
        }

        return bin2hex(random_bytes(8));
    }

    private function isValidId(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        return preg_match('/^[A-Za-z0-9._-]{1,64}$/', $value) === 1;
    }
}
