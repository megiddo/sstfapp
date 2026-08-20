<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

final readonly class AccountSnapshot
{
    /**
     * @param list<string> $providers
     */
    public function __construct(
        public string $email,
        public string $timezone,
        public string $weightUnit,
        public array $providers,
    ) {
    }

    /**
     * @return array{email: string, timezone: string, weight_unit: string, identities: list<array{provider: string}>}
     */
    public function toApi(): array
    {
        $identities = [];
        foreach ($this->providers as $provider) {
            $identities[] = ['provider' => $provider];
        }

        return [
            'email' => $this->email,
            'timezone' => $this->timezone,
            'weight_unit' => $this->weightUnit,
            'identities' => $identities,
        ];
    }
}
