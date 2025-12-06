<?php

namespace App\Services;

use GuzzleHttp\Client;

class OpenAIService
{
    private Client $httpClient;
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->httpClient = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . ($_ENV['OPENAI_API_KEY'] ?? ''),
                'Content-Type' => 'application/json',
            ],
        ]);
        $config = require __DIR__ . '/../../config/config.php';
        $this->apiKey = $config['openai']['api_key'];
        $this->model = $config['openai']['model'];
    }

    public function analyzeDialog(string $dialogText, bool $enhanced = false): array
    {
        $systemPrompt = $enhanced 
            ? $this->getEnhancedSystemPrompt()
            : $this->getStandardSystemPrompt();

        $response = $this->httpClient->post('chat/completions', [
            'json' => [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $dialogText],
                ],
                'temperature' => 0.7,
                'response_format' => ['type' => 'json_object'],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        $content = $data['choices'][0]['message']['content'] ?? '';
        
        $result = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON response from OpenAI');
        }

        return $result;
    }

    private function getStandardSystemPrompt(): string
    {
        return <<<PROMPT
Ты — AI-ассистент для анализа переписок. Твоя задача — проанализировать диалог и вернуть структурированный JSON-ответ.

Верни JSON строго по следующей схеме:
{
  "short_summary": "Краткое резюме диалога (2-3 предложения)",
  "messages": [
    {
      "text": "Текст сообщения",
      "author": "имя автора или 'user'",
      "tone": "neutral|warm|cold|irritated|anxious",
      "sentiment": "positive|neutral|negative"
    }
  ],
  "issues": [
    "Проблемное место 1",
    "Проблемное место 2"
  ],
  "reply_options": [
    {
      "type": "soft",
      "text": "Мягкий вариант ответа"
    },
    {
      "type": "direct",
      "text": "Прямой вариант ответа"
    },
    {
      "type": "humor",
      "text": "Вариант с юмором"
    },
    {
      "type": "boundaries",
      "text": "Вариант с установкой границ"
    }
  ]
}

Тональность (tone):
- neutral — нейтральный
- warm — тёплый
- cold — холодный
- irritated — раздражение
- anxious — тревожность

Важно: верни только валидный JSON, без дополнительного текста.
PROMPT;
    }

    private function getEnhancedSystemPrompt(): string
    {
        return $this->getStandardSystemPrompt() . "\n\nДополнительно: проведи более глубокий анализ тональности и эмоциональных нюансов. Укажи более детальные рекомендации по коммуникации.";
    }
}

