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
    // CRITICAL: Never log passwords in production
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        $defaultPassword = bin2hex(random_bytes(16));
        error_log("DEVELOPMENT: Generated temporary admin password: $defaultPassword");
        error_log("DEVELOPMENT: Set ADMIN_PASSWORD environment variable before production deployment!");
    } else {
        // In production, require explicit password setting
        die("SECURITY ERROR: ADMIN_PASSWORD environment variable must be set in production. Please configure a secure admin password.");
    }
}
define('ADMIN_PASSWORD', $defaultPassword);

// Logging configuration
define('ENABLE_API_LOGGING', ($_ENV['ENABLE_API_LOGGING'] ?? $_SERVER['ENABLE_API_LOGGING'] ?? 'true') === 'true');
define('ENABLE_SECURITY_LOGGING', ($_ENV['ENABLE_SECURITY_LOGGING'] ?? $_SERVER['ENABLE_SECURITY_LOGGING'] ?? 'true') === 'true');

define('ALLOWED_ORIGINS', $_ENV['ALLOWED_ORIGINS'] ?? $_SERVER['ALLOWED_ORIGINS'] ?? 'https://localhost,https://127.0.0.1'); // Comma-separated list of allowed origins

// Rate limiting configuration
define('API_RATE_LIMIT', (int)($_ENV['API_RATE_LIMIT'] ?? $_SERVER['API_RATE_LIMIT'] ?? 60)); // Requests per time window
define('API_RATE_WINDOW', (int)($_ENV['API_RATE_WINDOW'] ?? $_SERVER['API_RATE_WINDOW'] ?? 300)); // Time window in seconds (5 minutes)
define('LOGIN_RATE_LIMIT', (int)($_ENV['LOGIN_RATE_LIMIT'] ?? $_SERVER['LOGIN_RATE_LIMIT'] ?? 10)); // Login attempts per time window
define('LOGIN_RATE_WINDOW', (int)($_ENV['LOGIN_RATE_WINDOW'] ?? $_SERVER['LOGIN_RATE_WINDOW'] ?? 300)); // Login time window in seconds (5 minutes)

// Session configuration
define('SESSION_TIMEOUT', (int)($_ENV['SESSION_TIMEOUT'] ?? $_SERVER['SESSION_TIMEOUT'] ?? 3600)); // Session timeout in seconds (1 hour)
define('SESSION_REGENERATION_INTERVAL', (int)($_ENV['SESSION_REGENERATION_INTERVAL'] ?? $_SERVER['SESSION_REGENERATION_INTERVAL'] ?? 1800)); // Session regeneration interval in seconds (30 minutes)
define('CSRF_TOKEN_LIFETIME', (int)($_ENV['CSRF_TOKEN_LIFETIME'] ?? $_SERVER['CSRF_TOKEN_LIFETIME'] ?? 3600)); // CSRF token lifetime in seconds (1 hour)

// Database configuration
define('DB_WAIT_TIMEOUT', (int)($_ENV['DB_WAIT_TIMEOUT'] ?? $_SERVER['DB_WAIT_TIMEOUT'] ?? 30)); // Database wait timeout in seconds
define('DB_INTERACTIVE_TIMEOUT', (int)($_ENV['DB_INTERACTIVE_TIMEOUT'] ?? $_SERVER['DB_INTERACTIVE_TIMEOUT'] ?? 30)); // Database interactive timeout in seconds

// Logging configuration
define('LOG_MAX_SIZE', (int)($_ENV['LOG_MAX_SIZE'] ?? $_SERVER['LOG_MAX_SIZE'] ?? 10485760)); // Maximum log file size in bytes (10MB)
define('LOGIN_DELAY_MICROSECONDS', (int)($_ENV['LOGIN_DELAY_MICROSECONDS'] ?? $_SERVER['LOGIN_DELAY_MICROSECONDS'] ?? 500000)); // Login delay in microseconds (0.5 seconds)

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
