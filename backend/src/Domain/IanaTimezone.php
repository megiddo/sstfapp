<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use DateTimeZone;
use Exception;

final class IanaTimezone
{
    public static function resolve(?string $name): string
    {
        if ($name === null) {
            return 'UTC';
        }

        $trimmed = trim($name);
        if ($trimmed === '') {
            return 'UTC';
        }

        try {
            new DateTimeZone($trimmed);
        } catch (Exception) {
            return 'UTC';
        }

        $identifiers = DateTimeZone::listIdentifiers();
        if (!in_array($trimmed, $identifiers, true)) {
            return 'UTC';
        }

        return $trimmed;
    }
}
