<?php

declare(strict_types=1);

namespace Sstf\Api\Infrastructure\Google;

use Sstf\Api\Domain\VerifiedGoogleUser;

interface GoogleIdTokenVerifierInterface
{
    public function verify(string $idToken): VerifiedGoogleUser;
}
