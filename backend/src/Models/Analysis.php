<?php

namespace App\Models;

use App\Database;
use PDO;

class Analysis
{
    public static function create(int $userId, string $sessionId, string $inputText, array $resultJson): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO analyses (user_id, session_id, input_text, result_json)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([
            $userId,
            $sessionId,
            $inputText,
            json_encode($resultJson, JSON_UNESCAPED_UNICODE),
        ]);
        return (int) $db->lastInsertId();
    }

    public static function getByUserId(int $userId, int $limit = 50, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            SELECT id, session_id, input_text, result_json, created_at
            FROM analyses
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ');
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll();
    }

    public static function getBySessionId(string $sessionId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM analyses WHERE session_id = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$sessionId]);
        return $stmt->fetch() ?: null;
    }
}

