<?php

declare(strict_types=1);

namespace Sstf\Api\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

final class JsonResponder
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function data(array $payload, int $status = 200): ResponseInterface
    {
        return self::json(['data' => $payload], $status);
    }

    public static function error(string $code, string $message, int $status): ResponseInterface
    {
        if ($status < 400 || $status > 599) {
            throw new \InvalidArgumentException('Error responses must use an HTTP 4xx or 5xx status');
        }

        return self::json([
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): ResponseInterface
    {
        $response = new Response($status);
        $response->getBody()->write((string) json_encode($payload, JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
