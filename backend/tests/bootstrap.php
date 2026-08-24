<?php

declare(strict_types=1);

$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'false';
$_ENV['DATA_PATH'] = sys_get_temp_dir() . '/sstf-data-' . getmypid();
$_ENV['SESSION_SECRET'] = 'testing-session-secret-key';
$_ENV['GOOGLE_CLIENT_ID'] = 'test-google-client-id.apps.googleusercontent.com';
$_ENV['GOOGLE_CLIENT_SECRET'] = 'test-google-client-secret';
$_ENV['GOOGLE_REDIRECT_URI'] = 'http://localhost:5173/api/auth/google/callback';
$_ENV['SESSION_PATH'] = $_ENV['DATA_PATH'] . '/sessions';

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('DATA_PATH=' . $_ENV['DATA_PATH']);
putenv('SESSION_SECRET=' . $_ENV['SESSION_SECRET']);
putenv('GOOGLE_CLIENT_ID=' . $_ENV['GOOGLE_CLIENT_ID']);
putenv('GOOGLE_CLIENT_SECRET=' . $_ENV['GOOGLE_CLIENT_SECRET']);
putenv('GOOGLE_REDIRECT_URI=' . $_ENV['GOOGLE_REDIRECT_URI']);
putenv('SESSION_PATH=' . $_ENV['SESSION_PATH']);

if (!is_dir($_ENV['DATA_PATH'])) {
    mkdir($_ENV['DATA_PATH'] . '/users', 0700, true);
}

require dirname(__DIR__) . '/vendor/autoload.php';
