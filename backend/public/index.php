<?php

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

// Загрузить переменные окружения
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Создать приложение
$app = AppFactory::create();

// Middleware для парсинга JSON
$app->addBodyParsingMiddleware();

// Middleware для CORS
$app->add(function (Request $request, $handler): Response {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $_ENV['FRONTEND_URL'] ?? '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
});

// OPTIONS handler для CORS
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

// Подключить маршруты
$routes = require __DIR__ . '/../src/routes.php';
$routes($app);

$app->run();

