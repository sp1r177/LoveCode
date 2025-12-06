<?php

use Slim\App;
use App\Middleware\AuthMiddleware;
use App\Controllers\AuthController;
use App\Controllers\AnalysisController;
use App\Controllers\ProfileController;
use App\Controllers\PaymentController;

return function (App $app) {
    // Авторизация
    $app->post('/api/auth/vk-init', [AuthController::class, 'init']);
    $app->get('/api/auth/vk-callback', [AuthController::class, 'callback']);

    // Анализ (требует авторизации)
    $app->post('/api/analyze-dialog', [AnalysisController::class, 'analyze'])
        ->add(new AuthMiddleware());

    // Профиль (требует авторизации)
    $app->get('/api/profile', [ProfileController::class, 'getProfile'])
        ->add(new AuthMiddleware());
    
    $app->get('/api/history', [ProfileController::class, 'getHistory'])
        ->add(new AuthMiddleware());

    // Платежи (требует авторизации)
    $app->post('/api/payment/create-session', [PaymentController::class, 'createSession'])
        ->add(new AuthMiddleware());
    
    $app->post('/api/payment/webhook', [PaymentController::class, 'webhook']);
    
    $app->get('/api/payment/verify/{payment_id}', [PaymentController::class, 'verify']);
};

