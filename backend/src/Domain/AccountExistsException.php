<?php

declare(strict_types=1);

namespace Sstf\Api\Domain;

use RuntimeException;

final class AccountExistsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('account_exists');
    }
}
