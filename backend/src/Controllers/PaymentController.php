<?php

namespace App\Controllers;

use App\Services\YooMoneyService;
use App\Models\Subscription;
use Slim\Routing\RouteContext;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PaymentController
{
    public function createSession(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();
        $plan = $body['plan'] ?? '';

        if (!in_array($plan, ['pro', 'ultra'])) {
            $response->getBody()->write(json_encode(['error' => 'Invalid plan']));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        try {
            $yoomoney = new YooMoneyService();
            $paymentUrl = $yoomoney->createPaymentLink($userId, $plan);

            $response->getBody()->write(json_encode(['payment_url' => $paymentUrl]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    public function verify(Request $request, Response $response): Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();
        $args = $route->getArguments();
        $paymentId = $args['payment_id'] ?? '';

        if (empty($paymentId)) {
            $response->getBody()->write(json_encode(['error' => 'Payment ID required']));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $db = \App\Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM payments WHERE payment_id = ?');
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();

        if (!$payment) {
            $response->getBody()->write(json_encode(['error' => 'Payment not found']));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        // Если платеж завершен, активировать подписку
        if ($payment['status'] === 'completed') {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
            Subscription::create($payment['user_id'], $payment['plan'], $expiresAt);
        }

        $response->getBody()->write(json_encode([
            'payment_id' => $payment['payment_id'],
            'status' => $payment['status'],
            'plan' => $payment['plan'],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function webhook(Request $request, Response $response): Response
    {
        // В реальном проекте здесь должна быть обработка webhook от ЮMoney
        // Для MVP можно оставить пустым или реализовать базовую проверку
        
        $body = $request->getParsedBody();
        $paymentId = $body['label'] ?? $body['payment_id'] ?? '';

        if (empty($paymentId)) {
            $response->getBody()->write(json_encode(['error' => 'Payment ID required']));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        $db = \App\Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM payments WHERE payment_id = ?');
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();

        if ($payment) {
            // Обновить статус платежа
            $updateStmt = $db->prepare('UPDATE payments SET status = "completed", payment_data = ? WHERE payment_id = ?');
            $updateStmt->execute([json_encode($body), $paymentId]);

            // Активировать подписку
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 month'));
            Subscription::create($payment['user_id'], $payment['plan'], $expiresAt);
        }

        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

