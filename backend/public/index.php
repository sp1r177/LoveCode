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

// Middleware для CORS (должен быть первым)
$app->add(function (Request $request, $handler): Response {
    // Parse allowed origins from env
    $allowedOrigins = explode(',', $_ENV['CORS_ORIGINS'] ?? 'https://flirt-ai.ru,https://www.flirt-ai.ru');
    $origin = $request->getHeaderLine('Origin');
    
    // Check if origin is allowed
    $allowedOrigin = '*';
    if (in_array($origin, $allowedOrigins)) {
        $allowedOrigin = $origin;
    } else if (count($allowedOrigins) > 0) {
        $allowedOrigin = $allowedOrigins[0];
    }
    
    // Handle OPTIONS preflight requests
    if ($request->getMethod() === 'OPTIONS') {
        // Log OPTIONS requests for debugging
        error_log('OPTIONS request received for: ' . $request->getUri()->getPath());
        
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-API-Key')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Max-Age', '3600')
            ->withStatus(204);
    }
    
    // Log all requests for debugging
    error_log('Request received: ' . $request->getMethod() . ' ' . $request->getUri()->getPath());
    
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-API-Key')
        ->withHeader('Access-Control-Allow-Credentials', 'true');
});

// Подключить маршруты
$routes = require __DIR__ . '/../src/routes.php';
$routes($app);

$app->run();