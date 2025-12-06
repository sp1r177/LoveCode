<?php

namespace App\Models;

use App\Database;
use PDO;

class User
{
    public static function findByVkId(int $vkId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE vk_id = ?');
        $stmt->execute([$vkId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function create(array $data): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO users (vk_id, first_name, last_name, avatar_url, email)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $data['vk_id'],
            $data['first_name'],
            $data['last_name'],
            $data['avatar_url'] ?? null,
            $data['email'] ?? null,
        ]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $userId, array $data): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            UPDATE users 
            SET first_name = ?, last_name = ?, avatar_url = ?, email = ?
            WHERE id = ?
        ');
        return $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['avatar_url'] ?? null,
            $data['email'] ?? null,
            $userId,
        ]);
    }

    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }
}

