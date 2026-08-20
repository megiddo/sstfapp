<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sstf\Api\Domain\InvalidTimezoneException;
use Sstf\Api\Domain\InvalidWeightUnitException;
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

    public function patch(Request $request, Response $response): Response
    {
        $hash = $request->getAttribute('email_hash');
        if (!is_string($hash) || $hash === '') {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::error('invalid_request', 'Invalid account update', 400);
        }

        $timezone = null;
        $hasTimezone = array_key_exists('timezone', $body);
        if ($hasTimezone) {
            if (!is_string($body['timezone'])) {
                return JsonResponder::error('invalid_timezone', 'Invalid timezone', 400);
            }
            $timezone = $body['timezone'];
        }

        $weightUnit = null;
        $hasUnit = array_key_exists('weight_unit', $body);
        if ($hasUnit) {
            if (!is_string($body['weight_unit'])) {
                return JsonResponder::error('invalid_weight_unit', 'Invalid weight unit', 400);
            }
            $weightUnit = $body['weight_unit'];
        }

        try {
            $account = $this->auth->updateMe($hash, $timezone, $weightUnit);
        } catch (UnauthenticatedException) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        } catch (InvalidTimezoneException) {
            return JsonResponder::error('invalid_timezone', 'Invalid timezone', 400);
        } catch (InvalidWeightUnitException) {
            return JsonResponder::error('invalid_weight_unit', 'Invalid weight unit', 400);
        }

        return JsonResponder::data($account->toApi());
    }
}
