<?php

declare(strict_types=1);

namespace Sstf\Api\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Sstf\Api\Domain\DuplicateExerciseNameException;
use Sstf\Api\Domain\InvalidExerciseException;
use Sstf\Api\Http\JsonResponder;
use Sstf\Api\Services\ExerciseService;
use Sstf\Api\Services\SuggestedExerciseService;

final class ExerciseController
{
    public function __construct(
        private readonly ExerciseService $exercises,
        private readonly SuggestedExerciseService $suggestions,
    ) {
    }

    public function index(Request $request, Response $response): Response
    {
        if (!$this->isAuthenticated($request)) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        $items = $this->exercises->list($this->queryTerm($request));
        $payload = [];
        foreach ($items as $item) {
            $payload[] = $item->toApi();
        }

        return JsonResponder::data(['exercises' => $payload]);
    }

    public function suggested(Request $request, Response $response): Response
    {
        $hash = $this->emailHash($request);
        if ($hash === null) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        $suggested = $this->suggestions->forUser($hash);
        $recent = [];
        foreach ($suggested['recent'] as $item) {
            $recent[] = $item->toApi();
        }
        $frequent = [];
        foreach ($suggested['frequent'] as $item) {
            $frequent[] = $item->toApi();
        }

        return JsonResponder::data([
            'recent' => $recent,
            'frequent' => $frequent,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        if (!$this->isAuthenticated($request)) {
            return JsonResponder::error('unauthenticated', 'Authentication required', 401);
        }

        $body = $request->getParsedBody();
        if (!is_array($body)) {
            return JsonResponder::error('invalid_request', 'Exercise name is required', 400);
        }

        $name = $body['name'] ?? null;
        if (!is_string($name)) {
            return JsonResponder::error('invalid_request', 'Exercise name is required', 400);
        }

        $muscleGroup = $this->optionalField($body, 'muscle_group');
        $equipment = $this->optionalField($body, 'equipment');
        $notes = $this->optionalField($body, 'notes');
        if ($muscleGroup === false || $equipment === false || $notes === false) {
            return JsonResponder::error('invalid_request', 'Invalid exercise', 400);
        }

        try {
            $exercise = $this->exercises->create($name, $muscleGroup, $equipment, $notes);
        } catch (InvalidExerciseException) {
            return JsonResponder::error('invalid_request', 'Exercise name is required', 400);
        } catch (DuplicateExerciseNameException) {
            return JsonResponder::error('duplicate_name', 'Exercise name already exists', 409);
        }

        return JsonResponder::data($exercise->toApi());
    }

    private function isAuthenticated(Request $request): bool
    {
        return $this->emailHash($request) !== null;
    }

    private function emailHash(Request $request): ?string
    {
        $hash = $request->getAttribute('email_hash');
        if (!is_string($hash) || $hash === '') {
            return null;
        }

        return $hash;
    }

    private function queryTerm(Request $request): ?string
    {
        $q = $request->getQueryParams()['q'] ?? null;
        if (!is_string($q)) {
            return null;
        }

        return $q;
    }

    /**
     * @param array<mixed> $body
     */
    private function optionalField(array $body, string $key): string|false|null
    {
        if (!array_key_exists($key, $body)) {
            return null;
        }

        $value = $body[$key];
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            return false;
        }

        return $value;
    }
}
