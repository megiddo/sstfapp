<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sstf\Api\Domain\ExerciseNotOnSetException;
use Sstf\Api\Domain\InvalidHistoryFilterException;
use Sstf\Api\Domain\InvalidLogException;
use Sstf\Api\Domain\HistoryFilters;
use Sstf\Api\Domain\SetNotFoundException;
use Sstf\Api\Domain\UnauthenticatedException;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Services\LogService;

final class LogController
{
    public function __construct(
        private readonly LogService $logs,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        try {
            $filters = HistoryFilters::fromQuery($request->getQueryParams());
            $days = $this->logs->history($hash, $filters);
        } catch (InvalidHistoryFilterException) {
            return JsonResponder::error('invalid_request', 'Invalid history filter', 400);
        } catch (UnauthenticatedException) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        $payload = [];
        foreach ($days as $day) {
            $payload[] = $day->toApi();
        }

        return JsonResponder::data(['days' => $payload]);
    }

    public function create(Request $request, Response $response): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::error('invalid_request', 'Invalid log', 400);
        }

        $setId = $this->requirePositiveInt($body['set_id'] ?? null);
        $globalId = $this->requirePositiveInt($body['global_exercise_id'] ?? null);
        $weight = $this->requireWeight($body['weight'] ?? null);
        $reps = $this->requireReps($body['reps'] ?? null);
        if ($setId === null || $globalId === null || $weight === null || $reps === null) {
            return JsonResponder::error('invalid_request', 'Invalid log', 400);
        }

        $notes = null;
        if (array_key_exists('notes', $body) && $body['notes'] !== null) {
            if (!is_string($body['notes'])) {
                return JsonResponder::error('invalid_request', 'Invalid log', 400);
            }
            $notes = $body['notes'];
        }

        try {
            $log = $this->logs->create($hash, $setId, $globalId, $weight, $reps, $notes);
        } catch (UnauthenticatedException) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        } catch (InvalidLogException) {
            return JsonResponder::error('invalid_request', 'Invalid log', 400);
        } catch (SetNotFoundException) {
            return JsonResponder::error('not_found', 'Set not found', 404);
        } catch (ExerciseNotOnSetException) {
            return JsonResponder::error('invalid_request', 'Exercise not found', 400);
        }

        return JsonResponder::data($log->toApi());
    }

    private function emailHash(Request $request): ?string
    {
        $hash = $request->getAttribute('email_hash');
        if (!is_string($hash) || $hash === '') {
            return null;
        }

        return $hash;
    }

    private function requirePositiveInt(mixed $value): ?int
    {
        if (!is_int($value) || $value < 1) {
            return null;
        }

        return $value;
    }

    private function requireWeight(mixed $value): ?float
    {
        if (is_int($value)) {
            return (float) $value;
        }
        if (is_float($value) && is_finite($value)) {
            return $value;
        }

        return null;
    }

    private function requireReps(mixed $value): ?int
    {
        if (!is_int($value)) {
            return null;
        }

        return $value;
    }
}
