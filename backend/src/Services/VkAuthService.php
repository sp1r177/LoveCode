<?php

namespace App\Services;

use App\Models\User;
use GuzzleHttp\Client;

class VkAuthService
{
    private Client $httpClient;
    private array $config;

    public function __construct()
    {
        $this->httpClient = new Client();
        $config = require __DIR__ . '/../../config/config.php';
        $this->config = $config['vk'];
        
        // Валидация конфигурации
        if (empty($this->config['app_id'])) {
            throw new \RuntimeException('VK App ID is not configured. Set VK_APP_ID or VK_CLIENT_ID environment variable.');
        }
        if (empty($this->config['app_secret'])) {
            throw new \RuntimeException('VK App Secret is not configured. Set VK_APP_SECRET or VK_CLIENT_SECRET environment variable.');
        }
        if (empty($this->config['redirect_uri'])) {
            throw new \RuntimeException('VK Redirect URI is not configured. Set VK_REDIRECT_URI environment variable.');
        }
    }

    public function getAuthUrl(): string
    {
        $params = [
            'client_id' => $this->config['app_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'response_type' => 'code',
            'scope' => 'email',
            'v' => '5.131',
        ];

        return 'https://oauth.vk.com/authorize?' . http_build_query($params);
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = $this->httpClient->get('https://oauth.vk.com/access_token', [
            'query' => [
                'client_id' => $this->config['app_id'],
                'client_secret' => $this->config['app_secret'],
                'redirect_uri' => $this->config['redirect_uri'],
                'code' => $code,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        if (isset($data['error'])) {
            throw new \RuntimeException('VK OAuth error: ' . $data['error_description']);
        }

        return $data;
    }

    public function getUserInfo(string $accessToken): array
    {
        $response = $this->httpClient->get('https://api.vk.com/method/users.get', [
            'query' => [
                'access_token' => $accessToken,
                'fields' => 'photo_200',
                'v' => '5.131',
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        
        if (isset($data['error'])) {
            throw new \RuntimeException('VK API error: ' . $data['error']['error_msg']);
        }

        $user = $data['response'][0] ?? null;
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        return [
            'vk_id' => (int) $user['id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'avatar_url' => $user['photo_200'] ?? null,
            'email' => null, // VK может вернуть email в токене
        ];
    }

    public function getOrCreateUser(array $tokenData): array
    {
        $userInfo = $this->getUserInfo($tokenData['access_token']);
        
        $user = User::findByVkId($userInfo['vk_id']);
        
        if ($user) {
            // Обновить данные пользователя
            User::update($user['id'], $userInfo);
            return User::findById($user['id']);
        } else {
            // Создать нового пользователя
            $userId = User::create($userInfo);
            return User::findById($userId);
        }
    }
}

