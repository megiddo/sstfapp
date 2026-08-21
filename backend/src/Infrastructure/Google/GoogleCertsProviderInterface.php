<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Google;

interface GoogleCertsProviderInterface
{
    /**
     * @return array<string, string> Key ID to PEM certificate or public key
     */
    public function publicKeys(): array;
}
