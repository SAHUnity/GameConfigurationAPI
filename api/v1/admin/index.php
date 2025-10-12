<?php

/**
 * Admin API Router for Game Configuration API
 */

// Validate admin session
if (!$auth->validateAdminSession()) {
    ResponseHandler::error('Unauthorized', 'UNAUTHORIZED', 401);
}

// Get request path
$pathParts = array_slice(explode('/', trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/')), 3);
$endpoint = $pathParts[0] ?? '';

try {
    switch ($endpoint) {
        case 'games':
            require_once 'games.php';
            break;
        case 'config':
            require_once 'config.php';
            break;
        default:
            ResponseHandler::error('Admin endpoint not found', 'NOT_FOUND', 404);
    }
} catch (Exception $e) {
    error_log("Admin API Error: " . $e->getMessage());
    ResponseHandler::error('Internal server error', 'INTERNAL_ERROR', 500);
}
