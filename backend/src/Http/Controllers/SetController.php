<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sstf\Api\Domain\CatalogExerciseNotFoundException;
use Sstf\Api\Domain\InvalidSetException;
use Sstf\Api\Domain\SetNotFoundException;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Services\SetService;

final class SetController
{
    public function __construct(
        private readonly SetService $sets,
    ) {
    }

    /**
     * @param array<string, string> $args
     */
    public function patch(Request $request, Response $response, array $args): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }
        $id = $this->positiveId($args['id'] ?? null);
        if ($id === null) {
            return JsonResponder::error('not_found', 'Set not found', 404);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::error('invalid_request', 'Invalid set', 400);
        }

        /** @var array{name?: string, day_of_week?: int, start_minutes?: int, sort_order?: int} $fields */
        $fields = [];
        if (array_key_exists('name', $body)) {
            if (!is_string($body['name'])) {
                return JsonResponder::error('invalid_request', 'Invalid set', 400);
            }
            $fields['name'] = $body['name'];
        }
        if (array_key_exists('day_of_week', $body)) {
            $day = $this->requireInt($body['day_of_week']);
            if ($day === null) {
                return JsonResponder::error('invalid_request', 'Invalid set', 400);
            }
            $fields['day_of_week'] = $day;
        }
        if (array_key_exists('start_minutes', $body)) {
            $minutes = $this->requireInt($body['start_minutes']);
            if ($minutes === null) {
                return JsonResponder::error('invalid_request', 'Invalid set', 400);
            }
            $fields['start_minutes'] = $minutes;
        }
        if (array_key_exists('sort_order', $body)) {
            $order = $this->requireInt($body['sort_order']);
            if ($order === null) {
                return JsonResponder::error('invalid_request', 'Invalid set', 400);
            }
            $fields['sort_order'] = $order;
        }

        try {
            $set = $this->sets->patch($hash, $id, $fields);
        } catch (InvalidSetException) {
            return JsonResponder::error('invalid_request', 'Invalid set', 400);
        } catch (SetNotFoundException) {
            return JsonResponder::error('not_found', 'Set not found', 404);
        }

        return JsonResponder::data($set->toApi());
    }

    /**
     * @param array<string, string> $args
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }
        $id = $this->positiveId($args['id'] ?? null);
        if ($id === null) {
            return JsonResponder::error('not_found', 'Set not found', 404);
        }

        try {
            $this->sets->delete($hash, $id);
        } catch (SetNotFoundException) {
            return JsonResponder::error('not_found', 'Set not found', 404);
        }

        return JsonResponder::data(['ok' => true]);
    }

    /**
     * @param array<string, string> $args
     */
    public function replaceExercises(Request $request, Response $response, array $args): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }
        $id = $this->positiveId($args['id'] ?? null);
        if ($id === null) {
            return JsonResponder::error('not_found', 'Set not found', 404);
        }

        $body = $request->getParsedBody();
        if (!is_array($body) || !array_key_exists('exercises', $body) || !is_array($body['exercises'])) {
            return JsonResponder::error('invalid_request', 'Invalid exercises', 400);
        }

        $ids = [];
        foreach ($body['exercises'] as $item) {
            if (!is_array($item)) {
                return JsonResponder::error('invalid_request', 'Invalid exercises', 400);
            }
            $globalId = $this->requireInt($item['global_exercise_id'] ?? null);
            if ($globalId === null || $globalId < 1) {
                return JsonResponder::error('invalid_request', 'Invalid exercises', 400);
            }
            $ids[] = $globalId;
        }

        try {
            $set = $this->sets->replaceExercises($hash, $id, $ids);
        } catch (CatalogExerciseNotFoundException) {
            return JsonResponder::error('invalid_request', 'Exercise not found', 400);
        } catch (SetNotFoundException) {
            return JsonResponder::error('not_found', 'Set not found', 404);
        }

        return JsonResponder::data($set->toApi());
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

    private function requireInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return null;
    }
}
