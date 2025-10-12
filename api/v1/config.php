<?php

/**
 * Configuration API endpoints for Game Configuration API
 */

// Extract parameters from URL
$gameId = $resource ?? null;
$key = $id ?? null;
$category = $_GET['category'] ?? null;

// Validate API key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
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
        ResponseHandler::success([$config['config_key'] => $value]);
    } elseif ($category) {
        // Get configurations by category
        $stmt = $pdo->prepare("SELECT config_key, config_value FROM configurations WHERE game_id = ? AND category = ?");
        $stmt->execute([$game['id'], $category]);
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($configs as $config) {
            $result[$config['config_key']] = json_decode($config['config_value'], true);
        }

        ResponseHandler::success($result, ['category' => $category]);
    } else {
        // Get all configurations for the game
        $stmt = $pdo->prepare("SELECT config_key, config_value FROM configurations WHERE game_id = ?");
        $stmt->execute([$game['id']]);
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($configs as $config) {
            $result[$config['config_key']] = json_decode($config['config_value'], true);
        }

        ResponseHandler::success($result, ['game_id' => $game['game_id']]);
    }
}
