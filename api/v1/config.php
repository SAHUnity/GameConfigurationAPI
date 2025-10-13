<?php

/**
 * Configuration API endpoints for Game Configuration API
 */

// Extract parameters from URL
$gameId = $resource ?? null;
$key = $id ?? null;
$category = $_GET['category'] ?? null;

// Validate and sanitize inputs
$gameId = $security->validateInput($gameId, 'alphanumeric', 50);
$key = $security->validateInput($key, 'string', 255);
$category = $security->validateInput($category, 'string', 50);

// Validate API key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
$apiKey = $security->validateInput($apiKey, 'alphanumeric', 128);

if (!$apiKey) {
    ResponseHandler::error('Invalid API key format', 'INVALID_API_KEY', 401);
}

$game = $auth->validateApiKey($apiKey);

if (!$game) {
    ResponseHandler::error('Invalid API key', 'INVALID_API_KEY', 401);
}

// Verify game_id matches the authenticated game
if ($gameId && $gameId !== $game['game_id']) {
    ResponseHandler::error('Access denied', 'ACCESS_DENIED', 403);
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetRequest($pdo, $game, $key, $category);
            break;
        default:
            ResponseHandler::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    }
} catch (Exception $e) {
    error_log("Configuration API Error: " . $e->getMessage());
    ResponseHandler::error('Internal server error', 'INTERNAL_ERROR', 500);
}

/**
 * Handle GET requests for configuration data
 */
function handleGetRequest($pdo, $game, $key, $category)
{
    if ($key) {
        // Get specific configuration key
        $stmt = $pdo->prepare("SELECT config_key, config_value FROM configurations WHERE game_id = ? AND config_key = ?");
        $stmt->execute([$game['id'], $key]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            ResponseHandler::error('Configuration not found', 'CONFIG_NOT_FOUND', 404);
        }

        $value = json_decode($config['config_value'], true);
        if ($value === null && json_decode($config['config_value']) !== null) {
            ResponseHandler::error('Invalid configuration data', 'INVALID_DATA', 500);
        }

        ResponseHandler::success([$config['config_key'] => $value]);
    } elseif ($category) {
        // Get configurations by category
        $stmt = $pdo->prepare("SELECT config_key, config_value FROM configurations WHERE game_id = ? AND category = ? ORDER BY config_key");
        $stmt->execute([$game['id'], $category]);
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($configs as $config) {
            $value = json_decode($config['config_value'], true);
            $result[$config['config_key']] = ($value !== null) ? $value : null;
        }

        ResponseHandler::success($result, ['category' => $category, 'count' => count($result)]);
    } else {
        // Get all configurations for the game
        $stmt = $pdo->prepare("SELECT config_key, config_value FROM configurations WHERE game_id = ? ORDER BY category, config_key");
        $stmt->execute([$game['id']]);
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($configs as $config) {
            $value = json_decode($config['config_value'], true);
            $result[$config['config_key']] = ($value !== null) ? $value : null;
        }

        ResponseHandler::success($result, ['game_id' => $game['game_id'], 'count' => count($result)]);
    }
}
