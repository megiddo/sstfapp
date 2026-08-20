<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use RuntimeException;

final class InvalidExerciseException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('invalid_request');
    }
}
