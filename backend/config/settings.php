<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'sstf-api',
        'env' => $_ENV['APP_ENV'] ?? 'development',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN),
    ],
    'data' => [
        'path' => getenv('DATA_PATH') ?: ($_ENV['DATA_PATH'] ?? dirname(__DIR__, 2) . '/data'),
    ],
];
