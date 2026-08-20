<?php

declare(strict_types=1);

use Sstf\Api\Http\Controllers\HealthController;
use Sstf\Api\Http\JsonErrorHandler;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Services\HealthService;

$settings = require __DIR__ . '/settings.php';

$dataPath = rtrim((string) $settings['data']['path'], '/');

return [
    'settings' => $settings,

    HealthService::class => static fn (): HealthService => new HealthService(),

    HealthController::class => static function ($c): HealthController {
        return new HealthController($c->get(HealthService::class));
    },

    JsonErrorHandler::class => static function () use ($settings): JsonErrorHandler {
        return new JsonErrorHandler((bool) $settings['app']['debug']);
    },

    Migrator::class => static fn (): Migrator => new Migrator(),

    GlobalDb::class => static function ($c) use ($dataPath): GlobalDb {
        return new GlobalDb(
            $dataPath . '/global.sqlite',
            $c->get(Migrator::class),
            dirname(__DIR__) . '/migrations/global',
        );
    },

    UserDbFactory::class => static function ($c) use ($dataPath): UserDbFactory {
        return new UserDbFactory(
            $dataPath . '/users',
            $c->get(Migrator::class),
            dirname(__DIR__) . '/migrations/user',
        );
    },
];
