<?php

declare(strict_types=1);

namespace Sstf\Api\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

final class RedirectResponder
{
    public static function to(string $location): ResponseInterface
    {
        if ($location === '') {
            throw new \InvalidArgumentException('Redirect location cannot be empty');
        }

        $response = new Response(302);

        return $response->withHeader('Location', $location);
    }
}
