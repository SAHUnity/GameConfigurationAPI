<?php

use App\Config;
use App\Database;

require_once __DIR__ . '/autoload.php';

try {
    Config::load(__DIR__ . '/.env');
} catch (Exception $e) {
    echo "Error loading configuration: " . $e->getMessage() . "\n";
    echo "Please ensure .env exists.\n";
    exit(1);
}

echo "Initializing Database Schema...\n";

try {
    $pdo = Database::getInstance();
    
    // Table: games
    $pdo->exec("CREATE TABLE IF NOT EXISTS games (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        api_key VARCHAR(64) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_api_key (api_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table 'games' created/checked.\n";

    // Table: configurations
    $pdo->exec("CREATE TABLE IF NOT EXISTS configurations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        game_id INT NOT NULL,
        key_name VARCHAR(255) NOT NULL,
        value LONGTEXT,
        description TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
        INDEX idx_game_key (game_id, key_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table 'configurations' created/checked.\n";

    // Table: users (Admin)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Table 'users' created/checked.\n";

    // Create Default Admin User
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    if ($stmt->fetchColumn() == 0) {
        $passHash = password_hash('password', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute(['admin', $passHash]);
        echo "Default admin user created (user: admin, pass: password).\n";
    }

    echo "Schema setup completed successfully.\n";

} catch (Exception $e) {
    echo "Setup Failed: " . $e->getMessage() . "\n";
    exit(1);
}
