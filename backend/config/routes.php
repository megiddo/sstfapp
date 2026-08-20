<?php

declare(strict_types=1);

use Slim\App;
use Sstf\Api\Http\Controllers\HealthController;

return static function (App $app): void {
    $app->get('/api/health', HealthController::class);
};
