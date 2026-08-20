<?php

declare(strict_types=1);

use Slim\App;
use Sstf\Api\Http\Controllers\AuthController;
use Sstf\Api\Http\Controllers\ExerciseController;
use Sstf\Api\Http\Controllers\HealthController;
use Sstf\Api\Http\Controllers\MeController;
use Sstf\Api\Http\Middleware\RequireJsonContentType;

return static function (App $app): void {
    $app->get('/api/health', HealthController::class);
    $app->get('/api/me', [MeController::class, 'me']);
    $app->patch('/api/me', [MeController::class, 'patch']);
    $app->get('/api/exercises', [ExerciseController::class, 'index']);
    $app->post('/api/exercises', [ExerciseController::class, 'create']);
    $app->post('/api/auth/google', [AuthController::class, 'google'])
        ->add(RequireJsonContentType::class);
    $app->post('/api/auth/logout', [AuthController::class, 'logout'])
        ->add(RequireJsonContentType::class);
};
