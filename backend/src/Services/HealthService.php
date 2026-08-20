<?php

declare(strict_types=1);

namespace Sstf\Api\Services;

final class HealthService
{
    /**
     * @return array{ok: true}
     */
    public function status(): array
    {
        return ['ok' => true];
    }

    public function isHealthy(): bool
    {
        return $this->status()['ok'] === true;
    }
}
