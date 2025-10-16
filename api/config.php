<?php
// Database connection configuration

// Use PDO for database connection
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $host = DB_HOST;
        $dbname = DB_NAME;
        $username = DB_USER;
        $password = DB_PASS;
        
        try {
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }
    
    return $pdo;
}

// Function to initialize the database if it doesn't exist
function initializeDatabase() {
    try {
        // Connect to MySQL server without specifying database
        $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Now connect to the specific database
        $pdo = getDBConnection();
        
        // Create tables if they don't exist
        $tables = [
            "games" => "CREATE TABLE IF NOT EXISTS games (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                api_key VARCHAR(64) UNIQUE NOT NULL,
                description TEXT,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )",
            "configurations" => "CREATE TABLE IF NOT EXISTS configurations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                game_id INT NOT NULL,
                config_key VARCHAR(255) NOT NULL,
                config_value TEXT NOT NULL,
                description TEXT,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
                INDEX idx_game_config (game_id, config_key),
                UNIQUE KEY unique_game_config (game_id, config_key)
            )",
            "users" => "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        ];
        
        foreach ($tables as $tableName => $sql) {
            $pdo->exec($sql);
        }
        
        // Remove slug column if it exists
        try {
            $pdo->exec("ALTER TABLE games DROP COLUMN slug");
        } catch (PDOException $e) {
            // Column may not exist, continue
        }
        
        // Insert default admin user if none exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([ADMIN_USERNAME]);
        if ($stmt->fetchColumn() == 0) {
            $defaultPasswordHash = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email) VALUES (?, ?, ?)");
            $stmt->execute([ADMIN_USERNAME, $defaultPasswordHash, 'admin@example.com']);
            error_log("Default admin user created with username: " . ADMIN_USERNAME . ". Please change the default password immediately!");
        }
        
        // Generate API keys for existing games that don't have them
        $stmt = $pdo->prepare("SELECT id FROM games WHERE api_key IS NULL OR api_key = ''");
        $stmt->execute();
        $gamesWithoutApiKey = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($gamesWithoutApiKey) > 0) {
            require_once __DIR__ . '/functions.php';
            foreach ($gamesWithoutApiKey as $gameId) {
                $apiKey = generateApiKey();
                $stmt = $pdo->prepare("UPDATE games SET api_key = ? WHERE id = ?");
                $stmt->execute([$apiKey, $gameId]);
            }
        }
        
    } catch (PDOException $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        die("Database initialization failed. Please check your configuration.");
    }
}