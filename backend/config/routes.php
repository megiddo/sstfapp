<?php

declare(strict_types=1);

use Slim\App;
use Sstf\Api\Http\Controllers\AuthController;
use Sstf\Api\Http\Controllers\ExerciseController;
use Sstf\Api\Http\Controllers\HealthController;
use Sstf\Api\Http\Controllers\MeController;
use Sstf\Api\Http\Controllers\ScheduleController;
use Sstf\Api\Http\Controllers\ExportController;
use Sstf\Api\Http\Controllers\LogController;
use Sstf\Api\Http\Controllers\SetController;
use Sstf\Api\Http\Controllers\WorkoutController;
use Sstf\Api\Http\Middleware\RequireJsonContentType;

return static function (App $app): void {
    $json = RequireJsonContentType::class;

    $app->get('/api/health', HealthController::class);
    $app->get('/api/me', [MeController::class, 'me']);
    $app->patch('/api/me', [MeController::class, 'patch']);
    $app->get('/api/exercises', [ExerciseController::class, 'index']);
    $app->post('/api/exercises', [ExerciseController::class, 'create']);
    $app->post('/api/auth/google', [AuthController::class, 'google'])
        ->add($json);
    $app->post('/api/auth/logout', [AuthController::class, 'logout'])
        ->add($json);

    $app->get('/api/schedules', [ScheduleController::class, 'index']);
    $app->post('/api/schedules', [ScheduleController::class, 'create'])
        ->add($json);
    $app->patch('/api/schedules/{id}', [ScheduleController::class, 'patch'])
        ->add($json);
    $app->post('/api/schedules/{id}/activate', [ScheduleController::class, 'activate'])
        ->add($json);
    $app->delete('/api/schedules/{id}', [ScheduleController::class, 'delete'])
        ->add($json);
    $app->get('/api/schedules/{id}/sets', [ScheduleController::class, 'listSets']);
    $app->post('/api/schedules/{id}/sets', [ScheduleController::class, 'createSet'])
        ->add($json);

    $app->patch('/api/sets/{id}', [SetController::class, 'patch'])
        ->add($json);
    $app->delete('/api/sets/{id}', [SetController::class, 'delete'])
        ->add($json);
    $app->put('/api/sets/{id}/exercises', [SetController::class, 'replaceExercises'])
        ->add($json);

    $app->get('/api/workout/current', [WorkoutController::class, 'current']);
    $app->get('/api/workout/sets', [WorkoutController::class, 'sets']);
    $app->get('/api/logs', [LogController::class, 'index']);
    $app->post('/api/logs', [LogController::class, 'create'])
        ->add($json);
    $app->get('/api/export', [ExportController::class, 'download']);
};
