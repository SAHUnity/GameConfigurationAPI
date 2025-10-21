<?php
// Main configuration file

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $_ENV[$key] = $value;
        }
    }
}

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? $_SERVER['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? $_SERVER['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? $_SERVER['DB_NAME'] ?? 'game_config');

// Application settings
define('APP_NAME', 'Game Configuration API');
define('API_VERSION', '1.0');
define('BASE_URL', $_ENV['BASE_URL'] ?? $_SERVER['BASE_URL'] ?? '');

// Security - CRITICAL: Never use default passwords in production
define('ADMIN_USERNAME', $_ENV['ADMIN_USERNAME'] ?? $_SERVER['ADMIN_USERNAME'] ?? 'admin');

// More secure default password handling
$defaultPassword = $_ENV['ADMIN_PASSWORD'] ?? $_SERVER['ADMIN_PASSWORD'] ?? null;
if ($defaultPassword === null || $defaultPassword === 'SecurePassword123!') {
    // Generate a random secure password if none is provided or default is used
    $defaultPassword = bin2hex(random_bytes(16));
    error_log("SECURITY ALERT: No secure admin password provided. Generated temporary password: $defaultPassword");
    error_log("SECURITY ALERT: Set ADMIN_PASSWORD environment variable immediately!");
}
define('ADMIN_PASSWORD', $defaultPassword);

// Logging configuration
define('ENABLE_API_LOGGING', ($_ENV['ENABLE_API_LOGGING'] ?? $_SERVER['ENABLE_API_LOGGING'] ?? 'true') === 'true');
define('ENABLE_SECURITY_LOGGING', ($_ENV['ENABLE_SECURITY_LOGGING'] ?? $_SERVER['ENABLE_SECURITY_LOGGING'] ?? 'true') === 'true');

define('ALLOWED_ORIGINS', $_ENV['ALLOWED_ORIGINS'] ?? $_SERVER['ALLOWED_ORIGINS'] ?? 'https://localhost,https://127.0.0.1'); // Comma-separated list of allowed origins

// Environment detection
define('ENVIRONMENT', $_ENV['ENVIRONMENT'] ?? $_SERVER['ENVIRONMENT'] ?? 'production');

// Error reporting
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}
