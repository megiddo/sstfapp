<?php

declare(strict_types=1);

use Sstf\Api\Domain\ClockInterface;
use Sstf\Api\Domain\SystemClock;
use Sstf\Api\Http\Controllers\AuthController;
use Sstf\Api\Http\Controllers\ExerciseController;
use Sstf\Api\Http\Controllers\ExportController;
use Sstf\Api\Http\Controllers\HealthController;
use Sstf\Api\Http\Controllers\LogController;
use Sstf\Api\Http\Controllers\MeController;
use Sstf\Api\Http\Controllers\ScheduleController;
use Sstf\Api\Http\Controllers\SetController;
use Sstf\Api\Http\Controllers\WorkoutController;
use Sstf\Api\Http\JsonErrorHandler;
use Sstf\Api\Http\Middleware\AuthRateLimit;
use Sstf\Api\Http\Middleware\RequestLog;
use Sstf\Api\Http\Middleware\RequireJsonContentType;
use Sstf\Api\Http\Middleware\SecurityHeaders;
use Sstf\Api\Http\Middleware\SessionAuth;
use Sstf\Api\Infrastructure\Log\JsonLogger;
use Sstf\Api\Infrastructure\RateLimit\AuthRateLimiterInterface;
use Sstf\Api\Infrastructure\RateLimit\MemoryAuthRateLimiter;
use Sstf\Api\Infrastructure\Google\GoogleCertsProviderInterface;
use Sstf\Api\Infrastructure\Google\GoogleIdTokenVerifierInterface;
use Sstf\Api\Infrastructure\Google\GoogleJwtIdTokenVerifier;
use Sstf\Api\Infrastructure\Google\UrlGoogleCertsProvider;
use Sstf\Api\Infrastructure\Http\UrlFetcher;
use Sstf\Api\Infrastructure\Session\FileSessionStore;
use Sstf\Api\Infrastructure\Session\SessionCookie;
use Sstf\Api\Infrastructure\Session\SessionService;
use Sstf\Api\Infrastructure\Sqlite\ExerciseRepository;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;
use Sstf\Api\Infrastructure\Sqlite\LogRepository;
use Sstf\Api\Infrastructure\Sqlite\Migrator;
use Sstf\Api\Infrastructure\Sqlite\ScheduleRepository;
use Sstf\Api\Infrastructure\Sqlite\SetRepository;
use Sstf\Api\Infrastructure\Sqlite\UserDbFactory;
use Sstf\Api\Infrastructure\Sqlite\UserDirectory;
use Sstf\Api\Services\AuthService;
use Sstf\Api\Services\ExerciseService;
use Sstf\Api\Services\ExportService;
use Sstf\Api\Services\HealthService;
use Sstf\Api\Services\LogService;
use Sstf\Api\Services\ScheduleService;
use Sstf\Api\Services\SetService;
use Sstf\Api\Services\SuggestedExerciseService;
use Sstf\Api\Services\WorkoutService;

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

    JsonLogger::class => static function () use ($settings): JsonLogger {
        $enabled = ($settings['app']['env'] ?? 'development') !== 'testing';

        return JsonLogger::stderr($enabled);
    },

    SecurityHeaders::class => static fn (): SecurityHeaders => new SecurityHeaders(),

    RequestLog::class => static function ($c): RequestLog {
        return new RequestLog($c->get(JsonLogger::class));
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

    AuthService::class => static function ($c) use ($settings): AuthService {
        $password = $settings['password'];

        return new AuthService(
            $c->get(GoogleIdTokenVerifierInterface::class),
            $c->get(UserDirectory::class),
            $c->get(SessionService::class),
            [
                'memory_cost' => (int) $password['memory_cost'],
                'time_cost' => (int) $password['time_cost'],
                'threads' => (int) $password['threads'],
            ],
        );
    },

    AuthRateLimiterInterface::class => static function ($c) use ($settings): AuthRateLimiterInterface {
        $limit = $settings['auth_rate_limit'];

        return new MemoryAuthRateLimiter(
            (int) $limit['max'],
            (int) $limit['window_seconds'],
            $c->get(ClockInterface::class),
        );
    },

    AuthRateLimit::class => static function ($c): AuthRateLimit {
        return new AuthRateLimit($c->get(AuthRateLimiterInterface::class));
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

    ExerciseRepository::class => static function ($c): ExerciseRepository {
        return new ExerciseRepository(
            $c->get(GlobalDb::class),
            $c->get(ClockInterface::class),
        );
    },

    ExerciseService::class => static function ($c): ExerciseService {
        return new ExerciseService($c->get(ExerciseRepository::class));
    },

    SuggestedExerciseService::class => static function ($c): SuggestedExerciseService {
        return new SuggestedExerciseService($c->get(LogRepository::class));
    },

    ExerciseController::class => static function ($c): ExerciseController {
        return new ExerciseController(
            $c->get(ExerciseService::class),
            $c->get(SuggestedExerciseService::class),
        );
    },

    ScheduleRepository::class => static function ($c): ScheduleRepository {
        return new ScheduleRepository(
            $c->get(UserDbFactory::class),
            $c->get(ClockInterface::class),
        );
    },

    SetRepository::class => static function ($c): SetRepository {
        return new SetRepository(
            $c->get(UserDbFactory::class),
            $c->get(ClockInterface::class),
        );
    },

    ScheduleService::class => static function ($c): ScheduleService {
        return new ScheduleService($c->get(ScheduleRepository::class));
    },

    SetService::class => static function ($c): SetService {
        return new SetService(
            $c->get(SetRepository::class),
            $c->get(ExerciseRepository::class),
        );
    },

    ScheduleController::class => static function ($c): ScheduleController {
        return new ScheduleController(
            $c->get(ScheduleService::class),
            $c->get(SetService::class),
        );
    },

    SetController::class => static function ($c): SetController {
        return new SetController($c->get(SetService::class));
    },

    LogRepository::class => static function ($c): LogRepository {
        return new LogRepository(
            $c->get(UserDbFactory::class),
            $c->get(ClockInterface::class),
        );
    },

    WorkoutService::class => static function ($c): WorkoutService {
        return new WorkoutService(
            $c->get(ClockInterface::class),
            $c->get(UserDirectory::class),
            $c->get(ScheduleRepository::class),
            $c->get(SetRepository::class),
            $c->get(LogRepository::class),
        );
    },

    LogService::class => static function ($c): LogService {
        return new LogService(
            $c->get(UserDirectory::class),
            $c->get(ScheduleRepository::class),
            $c->get(SetRepository::class),
            $c->get(LogRepository::class),
        );
    },

    WorkoutController::class => static function ($c): WorkoutController {
        return new WorkoutController($c->get(WorkoutService::class));
    },

    LogController::class => static function ($c): LogController {
        return new LogController($c->get(LogService::class));
    },

    ExportService::class => static function ($c): ExportService {
        return new ExportService(
            $c->get(UserDirectory::class),
            $c->get(UserDbFactory::class),
        );
    },

    ExportController::class => static function ($c): ExportController {
        return new ExportController($c->get(ExportService::class));
    },

    SessionAuth::class => static function ($c): SessionAuth {
        return new SessionAuth($c->get(SessionService::class));
    },

    RequireJsonContentType::class => static fn (): RequireJsonContentType => new RequireJsonContentType(),
];
