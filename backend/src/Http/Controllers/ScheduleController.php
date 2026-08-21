<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sstf\Api\Domain\InvalidScheduleException;
use Sstf\Api\Domain\InvalidSetException;
use Sstf\Api\Domain\ScheduleNotFoundException;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Services\ScheduleService;
use Sstf\Api\Services\SetService;

final class ScheduleController
{
    public function __construct(
        private readonly ScheduleService $schedules,
        private readonly SetService $sets,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        $items = $this->schedules->list($hash);
        $payload = [];
        foreach ($items as $item) {
            $payload[] = $item->toApi();
        }

        return JsonResponder::data(['schedules' => $payload]);
    }

    public function create(Request $request, Response $response): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::error('invalid_request', 'Schedule name is required', 400);
        }
        $name = $body['name'] ?? null;
        if (!is_string($name)) {
            return JsonResponder::error('invalid_request', 'Schedule name is required', 400);
        }

        try {
            $schedule = $this->schedules->create($hash, $name);
        } catch (InvalidScheduleException) {
            return JsonResponder::error('invalid_request', 'Schedule name is required', 400);
        }

        return JsonResponder::data($schedule->toApi());
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
            return JsonResponder::error('not_found', 'Schedule not found', 404);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::error('invalid_request', 'Schedule name is required', 400);
        }
        $name = $body['name'] ?? null;
        if (!is_string($name)) {
            return JsonResponder::error('invalid_request', 'Schedule name is required', 400);
        }

        try {
            $schedule = $this->schedules->rename($hash, $id, $name);
        } catch (InvalidScheduleException) {
            return JsonResponder::error('invalid_request', 'Schedule name is required', 400);
        } catch (ScheduleNotFoundException) {
            return JsonResponder::error('not_found', 'Schedule not found', 404);
        }

        return JsonResponder::data($schedule->toApi());
    }

    /**
     * @param array<string, string> $args
     */
    public function activate(Request $request, Response $response, array $args): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }
        $id = $this->positiveId($args['id'] ?? null);
        if ($id === null) {
            return JsonResponder::error('not_found', 'Schedule not found', 404);
        }

        try {
            $schedule = $this->schedules->activate($hash, $id);
        } catch (ScheduleNotFoundException) {
            return JsonResponder::error('not_found', 'Schedule not found', 404);
        }

        return JsonResponder::data($schedule->toApi());
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
            return JsonResponder::error('not_found', 'Schedule not found', 404);
        }

        try {
            $this->schedules->archive($hash, $id);
        } catch (ScheduleNotFoundException) {
            return JsonResponder::error('not_found', 'Schedule not found', 404);
        }

        return JsonResponder::data(['ok' => true]);
    }

    /**
     * @param array<string, string> $args
     */
    public function listSets(Request $request, Response $response, array $args): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }
        $id = $this->positiveId($args['id'] ?? null);
        if ($id === null) {
            return JsonResponder::error('not_found', 'Schedule not found', 404);
        }

        try {
            $sets = $this->sets->listForSchedule($hash, $id);
        } catch (ScheduleNotFoundException) {
            return JsonResponder::error('not_found', 'Schedule not found', 404);
        }

        $payload = [];
        foreach ($sets as $set) {
            $payload[] = $set->toApi();
        }

        return JsonResponder::data(['sets' => $payload]);
    }

    /**
     * @param array<string, string> $args
     */
    public function createSet(Request $request, Response $response, array $args): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }
        $id = $this->positiveId($args['id'] ?? null);
        if ($id === null) {
            return JsonResponder::error('not_found', 'Schedule not found', 404);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::error('invalid_request', 'Invalid set', 400);
        }
        $name = $body['name'] ?? null;
        if (!is_string($name)) {
            return JsonResponder::error('invalid_request', 'Invalid set', 400);
        }
        $day = $this->requireInt($body['day_of_week'] ?? null);
        $minutes = $this->requireInt($body['start_minutes'] ?? null);
        $order = array_key_exists('sort_order', $body)
            ? $this->requireInt($body['sort_order'])
            : 0;
        if ($day === null || $minutes === null || $order === null) {
            return JsonResponder::error('invalid_request', 'Invalid set', 400);
        }

        try {
            $set = $this->sets->create($hash, $id, $name, $day, $minutes, $order);
        } catch (InvalidSetException) {
            return JsonResponder::error('invalid_request', 'Invalid set', 400);
        } catch (ScheduleNotFoundException) {
            return JsonResponder::error('not_found', 'Schedule not found', 404);
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
