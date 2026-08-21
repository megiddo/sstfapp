<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Http;

use InvalidArgumentException;
use RuntimeException;

final class UrlFetcher
{
    public function get(string $url): string
    {
        if ($url === '') {
            throw new InvalidArgumentException('URL cannot be empty');
        }

        $body = @file_get_contents($url);
        if ($body === false) {
            throw new RuntimeException('Unable to fetch URL: ' . $url);
        }

        return $body;
    }
}
