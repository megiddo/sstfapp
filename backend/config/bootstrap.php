<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Sstf\Api\Http\JsonErrorHandler;
use Sstf\Api\Http\Middleware\SessionAuth;
use Sstf\Api\Infrastructure\Sqlite\GlobalDb;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

if (is_readable($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
} elseif (is_readable(dirname($root) . '/.env')) {
    Dotenv\Dotenv::createImmutable(dirname($root))->safeLoad();
}

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(require __DIR__ . '/dependencies.php');
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

$settings = $container->get('settings');
$debug = (bool) $settings['app']['debug'];
$logErrors = ($settings['app']['env'] ?? 'development') !== 'testing';

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add($container->get(SessionAuth::class));
$errorMiddleware = $app->addErrorMiddleware($debug, $logErrors, $debug);
$errorMiddleware->setDefaultErrorHandler($container->get(JsonErrorHandler::class));

(require __DIR__ . '/routes.php')($app);

$container->get(GlobalDb::class)->connect();

return $app;
