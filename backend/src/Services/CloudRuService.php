<?php

namespace App\Services;

use GuzzleHttp\Client;

class CloudRuService
{
    private Client $httpClient;
    private string $apiKey;
    private string $model;
    private string $folderId;

    public function __construct()
    {
        $config = require __DIR__ . '/../../config/config.php';
        $cloudRu = $config['cloudru'];
        $this->apiKey = $cloudRu['api_key'];
        $this->folderId = $cloudRu['folder_id'];
        $modelName = $cloudRu['model'];
        
        // Формируем полный путь к модели для Cloud.ru
        $fullModelPath = "gpt://{$this->folderId}/{$modelName}/latest";
        
        $this->httpClient = new Client([
            'base_uri' => 'https://llm.api.cloud.yandex.net/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
        
        // Сохраняем полный путь модели
        $this->model = $fullModelPath;
    }

    public function analyzeDialog(string $dialogText, bool $enhanced = false): array
    {
        $systemPrompt = $enhanced 
            ? $this->getEnhancedSystemPrompt()
            : $this->getStandardSystemPrompt();

        // Cloud.ru использует формат совместимый с OpenAI API
        $response = $this->httpClient->post('chat/completions', [
            'json' => [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $dialogText],
                ],
                'temperature' => 0.7,
                'max_tokens' => 4000,
                // Cloud.ru может поддерживать response_format, если нет - уберём
                'response_format' => ['type' => 'json_object'],
            ],
            'timeout' => 60, // Увеличиваем таймаут для больших моделей
        ]);

        $responseBody = $response->getBody()->getContents();
        $data = json_decode($responseBody, true);
        
        if (isset($data['error'])) {
            throw new \RuntimeException('Cloud.ru API error: ' . ($data['error']['message'] ?? 'Unknown error'));
        }
        
        $content = $data['choices'][0]['message']['content'] ?? '';
        
        // Если ответ не JSON, попробуем извлечь JSON из текста
        $result = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Попробуем найти JSON в ответе
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                $result = json_decode($matches[0], true);
            }
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON response from Cloud.ru API. Response: ' . substr($content, 0, 200));
            }
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


