<?php

namespace App\Controllers;

use App\Services\VkAuthService;
use App\Services\JwtService;
use App\Models\Subscription;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function init(Request $request, Response $response): Response
    {
        try {
            $vkAuth = new VkAuthService();
            $authUrl = $vkAuth->getAuthUrl();
            
            if (empty($authUrl) || !filter_var($authUrl, FILTER_VALIDATE_URL)) {
                error_log('VK Auth URL is invalid or empty. Check VK_APP_ID/VK_CLIENT_ID and VK_REDIRECT_URI');
                $response->getBody()->write(json_encode(['error' => 'VK authentication not configured']));
                return $response
                    ->withStatus(500)
                    ->withHeader('Content-Type', 'application/json');
            }
            
            $response->getBody()->write(json_encode(['auth_url' => $authUrl]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log('VK Auth init error: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    public function callback(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $code = $queryParams['code'] ?? null;
        $error = $queryParams['error'] ?? null;

        if ($error) {
            $response->getBody()->write(json_encode(['error' => $error]));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        if (!$code) {
            $response->getBody()->write(json_encode(['error' => 'Code not provided']));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        try {
            $vkAuth = new VkAuthService();
            $tokenData = $vkAuth->exchangeCodeForToken($code);
            $user = $vkAuth->getOrCreateUser($tokenData);

            // Создать подписку Free, если её нет
            $subscription = Subscription::getActive($user['id']);
            if (!$subscription) {
                Subscription::create($user['id'], 'free');
            }

            // Сгенерировать JWT токен
            $jwtService = new JwtService();
            $token = $jwtService->generateToken($user['id']);

            $frontendUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost';
            $redirectUrl = $frontendUrl . '/auth/callback?token=' . urlencode($token);

            // Редирект на фронтенд
            return $response
                ->withStatus(302)
                ->withHeader('Location', $redirectUrl);
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    public function vkid(Request $request, Response $response): Response
    {
        // Логируем входящий запрос
        error_log('VK ID auth request received. Method: ' . $request->getMethod());
        
        // Добавляем заголовки CORS в начале
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $_ENV['FRONTEND_URL'] ?? '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Content-Type', 'application/json');

        // Обработка OPTIONS запроса для CORS
        if ($request->getMethod() === 'OPTIONS') {
            error_log('VK ID OPTIONS request - returning 204');
            return $response->withStatus(204);
        }

        // Проверяем метод запроса
        if ($request->getMethod() !== 'POST') {
            error_log('VK ID error: Wrong method - ' . $request->getMethod());
            $response->getBody()->write(json_encode(['error' => 'Method not allowed']));
            return $response->withStatus(405);
        }

        $body = $request->getParsedBody();
        error_log('VK ID request body: ' . json_encode($body));
        $accessToken = $body['access_token'] ?? null;

        error_log('VK ID token received: ' . ($accessToken ? 'yes' : 'no'));

        if (!$accessToken) {
            error_log('VK ID error: Access token not provided');
            $response->getBody()->write(json_encode(['error' => 'Access token not provided']));
            return $response->withStatus(400);
        }

        try {
            $vkAuth = new VkAuthService();
            error_log('Getting user info from VK...');
            $user = $vkAuth->authenticateWithVkIdToken($accessToken);
            error_log('User authenticated: ' . $user['id']);

            // Создать подписку Free, если её нет
            $subscription = Subscription::getActive($user['id']);
            if (!$subscription) {
                Subscription::create($user['id'], 'free');
            }

            // Сгенерировать JWT токен
            $jwtService = new JwtService();
            $token = $jwtService->generateToken($user['id']);

            error_log('JWT token generated successfully');
            $response->getBody()->write(json_encode(['token' => $token]));
            return $response;
        } catch (\Exception $e) {
            error_log('VK ID auth error: ' . $e->getMessage());
            error_log('VK ID auth error trace: ' . $e->getTraceAsString());
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(500);
        }
    }
}

