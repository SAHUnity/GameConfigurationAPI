<?php

namespace App\Models;

use App\Database;
use PDO;

class Configuration
{
    public static function getAllForGame(int $gameId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT id, key_name, value, description FROM configurations WHERE game_id = ? AND is_active = 1");
        $stmt->execute([$gameId]);
        return $stmt->fetchAll();
    }

    public static function create(int $gameId, string $key, string $value, string $desc = ''): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("INSERT INTO configurations (game_id, key_name, value, description) VALUES (?, ?, ?, ?)");
        $stmt->execute([$gameId, $key, $value, $desc]);
    }

    public static function update(int $id, string $value, string $desc): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("UPDATE configurations SET value = ?, description = ? WHERE id = ?");
        $stmt->execute([$value, $desc, $id]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("DELETE FROM configurations WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    // Helper to get game ID from config ID for cache clearing
    public static function getGameId(int $configId): ?int 
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT game_id FROM configurations WHERE id = ?");
        $stmt->execute([$configId]);
        return $stmt->fetchColumn() ?: null;
    }
}
