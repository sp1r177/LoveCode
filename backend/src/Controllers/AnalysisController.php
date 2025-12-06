<?php

namespace App\Controllers;

use App\Services\OpenAIService;
use App\Models\Analysis;
use App\Models\Subscription;
use App\Models\UsageLimit;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AnalysisController
{
    public function analyze(Request $request, Response $response): Response
    {
        $userId = $request->getAttribute('user_id');
        $body = $request->getParsedBody();
        $dialogText = $body['text'] ?? '';

        if (empty($dialogText)) {
            $response->getBody()->write(json_encode(['error' => 'Text is required']));
            return $response
                ->withStatus(400)
                ->withHeader('Content-Type', 'application/json');
        }

        // Проверить лимиты
        $subscription = Subscription::getActive($userId);
        $plan = $subscription ? $subscription['plan'] : 'free';
        $config = require __DIR__ . '/../../config/config.php';
        $planConfig = $config['plans'][$plan];

        $currentCount = UsageLimit::getCurrentMonthCount($userId);
        if ($currentCount >= $planConfig['analyses_per_month']) {
            $response->getBody()->write(json_encode([
                'error' => 'Monthly limit exceeded',
                'limit' => $planConfig['analyses_per_month'],
                'used' => $currentCount,
            ]));
            return $response
                ->withStatus(403)
                ->withHeader('Content-Type', 'application/json');
        }

        try {
            // Анализ через OpenAI
            $openai = new OpenAIService();
            $enhanced = $planConfig['enhanced_analysis'] ?? false;
            $result = $openai->analyzeDialog($dialogText, $enhanced);

            // Ограничить количество вариантов ответов для Free
            if ($planConfig['reply_options_count'] < 4) {
                $result['reply_options'] = array_slice($result['reply_options'], 0, $planConfig['reply_options_count']);
            }

            // Сохранить анализ
            $sessionId = bin2hex(random_bytes(16));
            Analysis::create($userId, $sessionId, $dialogText, $result);

            // Увеличить счётчик
            UsageLimit::increment($userId);

            $response->getBody()->write(json_encode([
                'session_id' => $sessionId,
                'result' => $result,
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response
                ->withStatus(500)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}

