<?php

namespace App\Models;

use App\Database;
use PDO;

class Subscription
{
    public static function getActive(int $userId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT * FROM subscriptions 
            WHERE user_id = ? AND status = "active" 
            AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $userId, string $plan, ?string $expiresAt = null): int
    {
        $db = Database::getInstance();
        
        // Деактивировать старые подписки
        $stmt = $db->prepare('UPDATE subscriptions SET status = "expired" WHERE user_id = ?');
        $stmt->execute([$userId]);

        // Создать новую
        $stmt = $db->prepare('
            INSERT INTO subscriptions (user_id, plan, status, expires_at)
            VALUES (?, ?, "active", ?)
        ');
        $stmt->execute([$userId, $plan, $expiresAt]);
        return (int) $db->lastInsertId();
    }

    public static function getPlan(int $userId): string
    {
        $subscription = self::getActive($userId);
        return $subscription ? $subscription['plan'] : 'free';
    }
}

