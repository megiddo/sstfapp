<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Http\FileResponder;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Services\ExportService;

final class ExportController
{
    public function __construct(
        private readonly ExportService $export,
    ) {
    }

    public function download(Request $request, Response $response): Response
    {
        $hash = $request->getAttribute('email_hash');
        if (!is_string($hash) || $hash === '') {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        try {
            $bytes = $this->export->bytes($hash);
        } catch (UnauthenticatedException) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        return FileResponder::download(
            $bytes,
            FileResponder::SQLITE_FILENAME,
            FileResponder::SQLITE_TYPE,
        );
    }
}
