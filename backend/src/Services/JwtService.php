<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtService
{
    private array $config;

    public function __construct()
    {
        $config = require __DIR__ . '/../../config/config.php';
        $this->config = $config['jwt'];
    }

    public function generateToken(int $userId): string
    {
        $payload = [
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + $this->config['expiration'],
        ];

        return JWT::encode($payload, $this->config['secret'], $this->config['algorithm']);
    }

    public function validateToken(string $token): ?int
    {
        try {
            $decoded = JWT::decode($token, new Key($this->config['secret'], $this->config['algorithm']));
            return $decoded->user_id ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}

