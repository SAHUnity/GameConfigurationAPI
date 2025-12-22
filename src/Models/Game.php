<?php

namespace App\Models;

use App\Database;
use PDO;

class Game
{
    public static function getByApiKey(string $apiKey): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT id, name, is_active FROM games WHERE api_key = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$apiKey]);
        return $stmt->fetch() ?: null;
    }

    public static function getAll(): array
    {
        $pdo = Database::getInstance();
        return $pdo->query("SELECT * FROM games ORDER BY created_at DESC")->fetchAll();
    }

    public static function create(string $name): string
    {
        $pdo = Database::getInstance();
        $apiKey = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("INSERT INTO games (name, api_key) VALUES (?, ?)");
        $stmt->execute([$name, $apiKey]);
        return $apiKey;
    }
    
    public static function regenerateKey(int $id): string
    {
        $pdo = Database::getInstance();
        $newKey = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("UPDATE games SET api_key = ? WHERE id = ?");
        $stmt->execute([$newKey, $id]);
        return $newKey;
    }

    public static function delete(int $id): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("DELETE FROM games WHERE id = ?");
        $stmt->execute([$id]);
    }
}
