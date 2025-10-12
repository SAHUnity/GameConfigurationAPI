<?php

/**
 * API Router for Game Configuration API
 */

// Load configuration and dependencies
require_once '../../../config/config.php';
require_once '../../../includes/ResponseHandler.php';
require_once '../../../includes/Auth.php';
require_once '../../../includes/RateLimiter.php';
require_once '../../../config/database.php';

// Initialize database and auth
$database = new Database();
$pdo = $database->getConnection();
$auth = new Auth($pdo);

// Rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';
if (!RateLimiter::checkLimit($clientIp)) {
    ResponseHandler::error('Rate limit exceeded', 'RATE_LIMIT_EXCEEDED', 429);
}

// Send rate limit headers
RateLimiter::sendRateLimitHeaders($clientIp);

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ResponseHandler::success(null);
}

// Get request path
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Route the request
array_shift($pathParts); // Remove 'api'
array_shift($pathParts); // Remove 'v1'

$endpoint = $pathParts[0] ?? '';
$resource = $pathParts[1] ?? '';
$id = $pathParts[2] ?? null;

try {
    switch ($endpoint) {
        case 'config':
            require_once 'config.php';
            break;
        case 'admin':
            require_once 'admin/index.php';
            break;
        default:
            ResponseHandler::error('Endpoint not found', 'NOT_FOUND', 404);
    }
} catch (Exception $e) {
    error_log("API Router Error: " . $e->getMessage());
    ResponseHandler::error('Internal server error', 'INTERNAL_ERROR', 500);
}
