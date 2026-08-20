<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Services\HealthService;

final class HealthController
{
    public function __construct(
        private readonly HealthService $healthService,
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        return JsonResponder::data($this->healthService->status());
    }
}
