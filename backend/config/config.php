<?php

return [
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'dbname' => $_ENV['DB_NAME'] ?? 'ai_assistant',
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
    ],
    'vk' => [
        'app_id' => $_ENV['VK_APP_ID'] ?? '',
        'app_secret' => $_ENV['VK_APP_SECRET'] ?? '',
        'redirect_uri' => $_ENV['VK_REDIRECT_URI'] ?? 'http://localhost/api/auth/vk-callback',
    ],
    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? 'your-secret-key-change-in-production',
        'algorithm' => 'HS256',
        'expiration' => 86400 * 30, // 30 days
    ],
    'openai' => [
        'api_key' => $_ENV['OPENAI_API_KEY'] ?? '',
        'model' => $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini',
    ],
    'yoomoney' => [
        'receiver' => $_ENV['YOOMONEY_RECEIVER'] ?? '',
        'secret' => $_ENV['YOOMONEY_SECRET'] ?? '',
    ],
    'plans' => [
        'free' => [
            'name' => 'Free',
            'analyses_per_month' => 5,
            'reply_options_count' => 2,
            'priority' => false,
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 299,
            'analyses_per_month' => 100,
            'reply_options_count' => 4,
            'priority' => true,
        ],
        'ultra' => [
            'name' => 'Ultra',
            'price' => 499,
            'analyses_per_month' => 500,
            'reply_options_count' => 4,
            'priority' => true,
            'enhanced_analysis' => true,
        ],
    ],
];

