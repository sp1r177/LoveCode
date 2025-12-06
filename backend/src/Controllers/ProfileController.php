<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Subscription;
use App\Models\Analysis;
use App\Models\UsageLimit;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProfileController
{
    public function getProfile(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        
        $user = User::findById($userId);
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
        }

        $subscription = Subscription::getActive($userId);
        $plan = $subscription ? $subscription['plan'] : 'free';
        $config = require __DIR__ . '/../../config/config.php';
        $planConfig = $config['plans'][$plan];

        $currentCount = UsageLimit::getCurrentMonthCount($userId);
        $limit = $planConfig['analyses_per_month'];

        $response->getBody()->write(json_encode([
            'user' => [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'avatar_url' => $user['avatar_url'],
            ],
            'subscription' => [
                'plan' => $plan,
                'name' => $planConfig['name'],
                'expires_at' => $subscription['expires_at'] ?? null,
            ],
            'usage' => [
                'used' => $currentCount,
                'limit' => $limit,
                'remaining' => max(0, $limit - $currentCount),
            ],
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function getHistory(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        $limit = (int) ($queryParams['limit'] ?? 50);
        $offset = (int) ($queryParams['offset'] ?? 0);

        $analyses = Analysis::getByUserId($userId, $limit, $offset);
        
        $result = array_map(function ($analysis) {
            return [
                'id' => $analysis['id'],
                'session_id' => $analysis['session_id'],
                'input_preview' => mb_substr($analysis['input_text'], 0, 100) . '...',
                'result' => json_decode($analysis['result_json'], true),
                'created_at' => $analysis['created_at'],
            ];
        }, $analyses);

        $response->getBody()->write(json_encode(['analyses' => $result]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

