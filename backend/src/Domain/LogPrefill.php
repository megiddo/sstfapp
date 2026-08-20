<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final readonly class LogPrefill
{
    public function __construct(
        public float $weight,
        public int $reps,
    ) {
    }
}
