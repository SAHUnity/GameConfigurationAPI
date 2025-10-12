<?php

/**
 * Application configuration for Game Configuration API
 */

// Application settings
define('APP_NAME', 'Game Configuration API');
define('APP_VERSION', '1.0.0');
define('API_VERSION', 'v1');

// Security settings
define('JWT_SECRET', 'your-secret-key-change-this-in-production');
define('SESSION_LIFETIME', 1800); // 30 minutes
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60); // seconds

// API Settings
define('CORS_ALLOWED_ORIGINS', '*'); // Restrict to your game domains in production
define('API_RESPONSE_FORMAT', 'json');

// File paths
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CACHE_PATH', ROOT_PATH . '/cache');
define('RATE_LIMIT_PATH', ROOT_PATH . '/rate_limits');

// Error reporting for development
if (getenv('ENVIRONMENT') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', ROOT_PATH . '/logs/error.log');
}

// Ensure required directories exist
if (!file_exists(CACHE_PATH)) {
    mkdir(CACHE_PATH, 0755, true);
}

if (!file_exists(RATE_LIMIT_PATH)) {
    mkdir(RATE_LIMIT_PATH, 0755, true);
}

if (!file_exists(ROOT_PATH . '/logs')) {
    mkdir(ROOT_PATH . '/logs', 0755, true);
}
