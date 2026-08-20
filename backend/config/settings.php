<?php

declare(strict_types=1);

$dataPath = getenv('DATA_PATH') ?: ($_ENV['DATA_PATH'] ?? dirname(__DIR__, 2) . '/data');
$dataPath = rtrim((string) $dataPath, '/');

return [
    'app' => [
        'name' => 'sstf-api',
        'env' => $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: true, FILTER_VALIDATE_BOOLEAN),
    ],
    'data' => [
        'path' => $dataPath,
    ],
    'google' => [
        'client_id' => (string) ($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: ''),
        'certs_url' => (string) ($_ENV['GOOGLE_CERTS_URL'] ?? getenv('GOOGLE_CERTS_URL') ?: 'https://www.googleapis.com/oauth2/v1/certs'),
    ],
    'session' => [
        'secret' => (string) ($_ENV['SESSION_SECRET'] ?? getenv('SESSION_SECRET') ?: ''),
        'path' => (string) ($_ENV['SESSION_PATH'] ?? getenv('SESSION_PATH') ?: $dataPath . '/sessions'),
        'cookie_name' => 'sstf_session',
        'secure' => (($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development') === 'production'),
    ],
];
