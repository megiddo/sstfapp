<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sstf\Api\Http\JsonResponder;

final class RequireJsonContentType implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = strtolower($request->getHeaderLine('Content-Type'));
        $mime = trim(explode(';', $header)[0]);
        if ($mime !== 'application/json') {
            return JsonResponder::error('invalid_content_type', 'JSON Content-Type required', 415);
        }

        return $handler->handle($request);
    }
}
