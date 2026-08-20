<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Services\AuthService;

final class MeController
{
    public function __construct(
        private readonly AuthService $auth,
    ) {
    }

    public function me(Request $request, Response $response): Response
    {
        $hash = $request->getAttribute('email_hash');
        if (!is_string($hash) || $hash === '') {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        try {
            $account = $this->auth->me($hash);
        } catch (UnauthenticatedException) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        return JsonResponder::data($account->toApi());
    }
}
