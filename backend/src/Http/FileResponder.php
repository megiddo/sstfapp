<?php

declare(strict_types=1);

namespace Sstf\Api\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

final class FileResponder
{
    public const SQLITE_FILENAME = 'sstf-data.sqlite';

    public const SQLITE_TYPE = 'application/vnd.sqlite3';

    public static function download(string $bytes, string $filename, string $contentType): ResponseInterface
    {
        $response = new Response(200);
        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string) strlen($bytes));
    }
}
