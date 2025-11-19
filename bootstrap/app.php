<?php

use DI\Container;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Create DI Container
$container = new Container();

// Configure Database (Eloquent ORM)
$capsule = new Capsule();
$capsule->addConnection(config('database.connections.mysql'));
$capsule->setAsGlobal();
$capsule->bootEloquent();

$container->set('db', function () use ($capsule) {
    return $capsule;
});

// Configure Logger
$logger = new Logger('splash-crm');
$logger->pushHandler(
    new StreamHandler(storage_path('logs/app.log'), Logger::DEBUG)
);

$container->set(Logger::class, function () use ($logger) {
    return $logger;
});

// Configure Redis
$redis = new Predis\Client([
    'scheme' => 'tcp',
    'host'   => config('database.redis.default.host'),
    'port'   => config('database.redis.default.port'),
]);

$container->set('redis', function () use ($redis) {
    return $redis;
});

// Create Slim App
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add middleware
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();

// Configure error handling
$errorMiddleware = $app->addErrorMiddleware(
    config('app.debug'),
    true,
    true
);

// Add CORS middleware
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

return $app;
