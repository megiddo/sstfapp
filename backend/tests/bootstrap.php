<?php

declare(strict_types=1);

$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'false';
$_ENV['DATA_PATH'] = sys_get_temp_dir() . '/sstf-data-' . getmypid();

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('DATA_PATH=' . $_ENV['DATA_PATH']);

if (!is_dir($_ENV['DATA_PATH'])) {
    mkdir($_ENV['DATA_PATH'] . '/users', 0700, true);
}

require dirname(__DIR__) . '/vendor/autoload.php';
