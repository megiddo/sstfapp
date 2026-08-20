<?php

declare(strict_types=1);

use Sstf\Api\Domain\ClockInterface;
use Sstf\Api\Domain\SystemClock;
use Sstf\Api\Http\Controllers\AuthController;
use Sstf\Api\Http\Controllers\HealthController;
use Sstf\Api\Http\Controllers\MeController;
use Sstf\Api\Http\JsonErrorHandler;
use Sstf\Api\Http\Middleware\RequireJsonContentType;
use Sstf\Api\Http\Middleware\SessionAuth;
use Sstf\Api\Infrastructure\Google\GoogleCertsProviderInterface;
use Sstf\Api\Infrastructure\Google\GoogleIdTokenVerifierInterface;
use Sstf\Api\Infrastructure\Google\GoogleJwtIdTokenVerifier;
use Sstf\Api\Infrastructure\Google\UrlGoogleCertsProvider;
use Sstf\Api\Infrastructure\Http\UrlFetcher;
use Sstf\Api\Infrastructure\Session\FileSessionStore;
use Sstf\Api\Infrastructure\Session\SessionCookie;
use Sstf\Api\Infrastructure\Session\SessionService;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;
use Sstf\Api\Services\AuthService;
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

    ClockInterface::class => static fn (): ClockInterface => new SystemClock(),

    UrlFetcher::class => static fn (): UrlFetcher => new UrlFetcher(),

    GoogleCertsProviderInterface::class => static function ($c) use ($settings): GoogleCertsProviderInterface {
        return new UrlGoogleCertsProvider(
            (string) $settings['google']['certs_url'],
            $c->get(UrlFetcher::class),
        );
    },

    GoogleIdTokenVerifierInterface::class => static function ($c) use ($settings): GoogleIdTokenVerifierInterface {
        return new GoogleJwtIdTokenVerifier(
            (string) $settings['google']['client_id'],
            $c->get(GoogleCertsProviderInterface::class),
            $c->get(ClockInterface::class),
        );
    },

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

    UserDirectory::class => static function ($c): UserDirectory {
        return new UserDirectory(
            $c->get(UserDbFactory::class),
            $c->get(GlobalDb::class),
            $c->get(ClockInterface::class),
        );
    },

    FileSessionStore::class => static function () use ($settings): FileSessionStore {
        return new FileSessionStore((string) $settings['session']['path']);
    },

    SessionCookie::class => static function () use ($settings): SessionCookie {
        return new SessionCookie(
            (string) $settings['session']['cookie_name'],
            (bool) $settings['session']['secure'],
        );
    },

    SessionService::class => static function ($c) use ($settings): SessionService {
        return new SessionService(
            $c->get(FileSessionStore::class),
            $c->get(SessionCookie::class),
            (string) $settings['session']['secret'],
        );
    },

    AuthService::class => static function ($c): AuthService {
        return new AuthService(
            $c->get(GoogleIdTokenVerifierInterface::class),
            $c->get(UserDirectory::class),
            $c->get(SessionService::class),
        );
    },

    AuthController::class => static function ($c): AuthController {
        return new AuthController(
            $c->get(AuthService::class),
            $c->get(SessionService::class),
        );
    },

    MeController::class => static function ($c): MeController {
        return new MeController($c->get(AuthService::class));
    },

    SessionAuth::class => static function ($c): SessionAuth {
        return new SessionAuth($c->get(SessionService::class));
    },

    RequireJsonContentType::class => static fn (): RequireJsonContentType => new RequireJsonContentType(),
];
