<?php

declare(strict_types=1);

$dataPath = getenv('DATA_PATH') ?: ($_ENV['DATA_PATH'] ?? dirname(__DIR__, 2) . '/data');
$dataPath = rtrim((string) $dataPath, '/');

$appEnv = (string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development');
$appUrl = rtrim((string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: ''), '/');
$sessionSecureRaw = $_ENV['SESSION_SECURE'] ?? getenv('SESSION_SECURE');
$sessionSecure = ($sessionSecureRaw === false || $sessionSecureRaw === null || $sessionSecureRaw === '')
    ? ($appEnv === 'production')
    : filter_var($sessionSecureRaw, FILTER_VALIDATE_BOOLEAN);
$defaultRateMax = $appEnv === 'testing' ? 10000 : 10;
$rateMaxRaw = $_ENV['AUTH_RATE_LIMIT_MAX'] ?? getenv('AUTH_RATE_LIMIT_MAX');
$rateMax = ($rateMaxRaw === false || $rateMaxRaw === null || $rateMaxRaw === '')
    ? $defaultRateMax
    : (int) $rateMaxRaw;
$rateWindowRaw = $_ENV['AUTH_RATE_LIMIT_WINDOW'] ?? getenv('AUTH_RATE_LIMIT_WINDOW');
$rateWindow = ($rateWindowRaw === false || $rateWindowRaw === null || $rateWindowRaw === '')
    ? 60
    : (int) $rateWindowRaw;

return [
    'app' => [
        'name' => 'sstf-api',
        'env' => $appEnv,
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: true, FILTER_VALIDATE_BOOLEAN),
        'url' => $appUrl,
    ],
    'data' => [
        'path' => $dataPath,
    ],
    'google' => [
        'client_id' => (string) ($_ENV['GOOGLE_CLIENT_ID'] ?? getenv('GOOGLE_CLIENT_ID') ?: ''),
        'client_secret' => (string) ($_ENV['GOOGLE_CLIENT_SECRET'] ?? getenv('GOOGLE_CLIENT_SECRET') ?: ''),
        'redirect_uri' => (string) ($_ENV['GOOGLE_REDIRECT_URI'] ?? getenv('GOOGLE_REDIRECT_URI') ?: 'http://localhost:27180/api/auth/google/callback'),
    ],
    'session' => [
        'secret' => (string) ($_ENV['SESSION_SECRET'] ?? getenv('SESSION_SECRET') ?: ''),
        'path' => (string) ($_ENV['SESSION_PATH'] ?? getenv('SESSION_PATH') ?: $dataPath . '/sessions'),
        'cookie_name' => 'sstf_session',
        'secure' => $sessionSecure,
    ],
    'auth_rate_limit' => [
        'max' => $rateMax,
        'window_seconds' => $rateWindow,
    ],
    'password' => [
        'memory_cost' => (int) ($_ENV['PASSWORD_ARGON2_MEMORY_COST'] ?? getenv('PASSWORD_ARGON2_MEMORY_COST') ?: ($appEnv === 'testing' ? 16 : 65536)),
        'time_cost' => (int) ($_ENV['PASSWORD_ARGON2_TIME_COST'] ?? getenv('PASSWORD_ARGON2_TIME_COST') ?: ($appEnv === 'testing' ? 1 : 4)),
        'threads' => (int) ($_ENV['PASSWORD_ARGON2_THREADS'] ?? getenv('PASSWORD_ARGON2_THREADS') ?: 1),
    ],
];
