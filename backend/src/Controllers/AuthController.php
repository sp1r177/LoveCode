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
}

