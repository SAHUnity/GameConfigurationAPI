<?php

namespace App\Models;

use App\Database;
use PDO;

class User
{
    public static function verify(string $username, string $password): ?array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            return $user;
        }

        return null;
    }
}
