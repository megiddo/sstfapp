<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Google;

use InvalidArgumentException;
use RuntimeException;
use Sstf\Api\Infrastructure\Http\UrlFetcher;

final class UrlGoogleCertsProvider implements GoogleCertsProviderInterface
{
    public function __construct(
        private readonly string $url,
        private readonly UrlFetcher $fetcher,
    ) {
        if ($this->url === '') {
            throw new InvalidArgumentException('Google certs URL cannot be empty');
        }
    }

    public function publicKeys(): array
    {
        $raw = $this->fetcher->get($this->url);
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Google certs JSON is invalid', 0, $exception);
        }

        if (!is_array($decoded) || $decoded === []) {
            throw new RuntimeException('Google certs payload must be a non-empty object');
        }

        $keys = [];
        foreach ($decoded as $kid => $pem) {
            if (!is_string($kid) || $kid === '' || !is_string($pem) || $pem === '') {
                throw new RuntimeException('Google certs payload has an invalid entry');
            }
            $keys[$kid] = $pem;
        }

        if ($keys === []) {
            throw new RuntimeException('Google certs payload must be a non-empty object');
        }

        return $keys;
    }
}
