<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sstf\Api\Domain\SetNotFoundException;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Services\WorkoutService;

final class WorkoutController
{
    public function __construct(
        private readonly WorkoutService $workouts,
    ) {
    }

    public function current(Request $request, Response $response): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        $override = $this->overrideSetId($request);
        if ($override === false) {
            return JsonResponder::error('not_found', 'Set not found', 404);
        }

        try {
            $workout = $this->workouts->current($hash, $override);
        } catch (UnauthenticatedException) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        } catch (SetNotFoundException) {
            return JsonResponder::error('not_found', 'Set not found', 404);
        }

        return JsonResponder::data($workout->toApi());
    }

    public function sets(Request $request, Response $response): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        try {
            $payload = $this->workouts->sets($hash);
        } catch (UnauthenticatedException) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        return JsonResponder::data($payload);
    }

    /**
     * @return int|null|false null = no override, false = malformed, int = override id
     */
    private function overrideSetId(Request $request): int|null|false
    {
        $query = $request->getQueryParams();
        if (!array_key_exists('set_id', $query)) {
            return null;
        }
        $raw = $query['set_id'];
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = $this->positiveId($raw);
        if ($id === null) {
            return false;
        }

        return $id;
    }

    private function emailHash(Request $request): ?string
    {
        $hash = $request->getAttribute('email_hash');
        if (!is_string($hash) || $hash === '') {
            return null;
        }

        return $hash;
    }

    private function positiveId(mixed $raw): ?int
    {
        if (is_int($raw) && $raw > 0) {
            return $raw;
        }
        if (!is_string($raw) || preg_match('/^[1-9][0-9]*$/', $raw) !== 1) {
            return null;
        }

        return (int) $raw;
    }
}
