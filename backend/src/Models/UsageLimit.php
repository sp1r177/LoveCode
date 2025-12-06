<?php

namespace App\Models;

use App\Database;
use PDO;

class UsageLimit
{
    public static function getCurrentMonthCount(int $userId): int
    {
        $db = Database::getInstance();
        $year = (int) date('Y');
        $month = (int) date('n');
        
        $stmt = $db->prepare('
            SELECT analyses_count FROM usage_limits
            WHERE user_id = ? AND year = ? AND month = ?
        ');
        $stmt->execute([$userId, $year, $month]);
        $result = $stmt->fetch();
        return $result ? (int) $result['analyses_count'] : 0;
    }

    public static function increment(int $userId): void
    {
        $db = Database::getInstance();
        $year = (int) date('Y');
        $month = (int) date('n');
        
        $stmt = $db->prepare('
            INSERT INTO usage_limits (user_id, year, month, analyses_count)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE analyses_count = analyses_count + 1
        ');
        $stmt->execute([$userId, $year, $month]);
    }
}

