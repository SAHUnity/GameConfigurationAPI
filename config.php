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

// Security
define('ADMIN_USERNAME', $_ENV['ADMIN_USERNAME'] ?? $_SERVER['ADMIN_USERNAME'] ?? 'admin');
define('ADMIN_PASSWORD', $_ENV['ADMIN_PASSWORD'] ?? $_SERVER['ADMIN_PASSWORD'] ?? 'SecurePassword123!'); // In production, change this default password and use proper hashing

// Check if using default password and log warning
if (defined('ADMIN_PASSWORD') && ADMIN_PASSWORD === 'SecurePassword123!') {
    error_log("WARNING: Default admin password detected in config.php. Change immediately for production use!");
}

// Logging configuration
define('ENABLE_API_LOGGING', ($_ENV['ENABLE_API_LOGGING'] ?? $_SERVER['ENABLE_API_LOGGING'] ?? 'true') === 'true');
define('ENABLE_SECURITY_LOGGING', ($_ENV['ENABLE_SECURITY_LOGGING'] ?? $_SERVER['ENABLE_SECURITY_LOGGING'] ?? 'true') === 'true');

define('ALLOWED_ORIGINS', $_ENV['ALLOWED_ORIGINS'] ?? $_SERVER['ALLOWED_ORIGINS'] ?? 'https://localhost,https://127.0.0.1'); // Comma-separated list of allowed origins

// Error reporting
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}