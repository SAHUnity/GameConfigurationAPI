<?php

/**
 * Admin Configuration Management API endpoints
 */

// Load utility functions
require_once '../../../includes/UtilityFunctions.php';

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetConfigurations($pdo, $security);
            break;
        case 'POST':
            handleCreateConfiguration($pdo, $security);
            break;
        case 'PUT':
            handleUpdateConfiguration($pdo, $security);
            break;
        case 'DELETE':
            handleDeleteConfiguration($pdo, $security);
            break;
        default:
            ResponseHandler::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    }
} catch (Exception $e) {
    error_log("Admin Config API Error: " . $e->getMessage());
    ResponseHandler::error('Internal server error', 'INTERNAL_ERROR', 500);
}

/**
 * Handle GET request - list configurations
 */
function handleGetConfigurations($pdo, $security)
{
    $gameId = $_GET['game_id'] ?? null;
    $category = $_GET['category'] ?? null;

    // Validate inputs
    $gameId = $security->validateInput($gameId, 'int');
    $category = $security->validateInput($category, 'string', 50);

    $sql = "
        SELECT c.*, g.name as game_name, g.game_id
        FROM configurations c
        JOIN games g ON c.game_id = g.id
        WHERE 1=1
    ";
    $params = [];

    if ($gameId && $gameId !== false) {
        $sql .= " AND c.game_id = ?";
        $params[] = $gameId;
    }

    if ($category && $category !== false) {
        $sql .= " AND c.category = ?";
        $params[] = $category;
    }

    $sql .= " ORDER BY c.category, c.config_key";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON values for display
    foreach ($configs as &$config) {
        $value = json_decode($config['config_value'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $config['config_value'] = $value;
        } else {
            $config['config_value'] = null; // Handle invalid JSON
        }
    }

    ResponseHandler::success($configs);
}

/**
 * Handle POST request - create new configuration
 */
function handleCreateConfiguration($pdo, $security)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        ResponseHandler::error('Invalid JSON input', 'INVALID_JSON', 400);
    }

    // Validate required fields
    if (empty($input['game_id']) || empty($input['config_key']) || !isset($input['config_value'])) {
        ResponseHandler::error('game_id, config_key, and config_value are required', 'INVALID_INPUT', 400);
    }

    // Validate and sanitize inputs
    $gameId = $security->validateInput($input['game_id'], 'int');
    $configKey = $security->validateInput($input['config_key'], 'string', 255);
    $category = $security->validateInput($input['category'] ?? 'general', 'string', 50);
    $description = $security->validateInput($input['description'] ?? null, 'string', 1000);

    if ($gameId === false || $configKey === false || $category === false || $description === false) {
        ResponseHandler::error('Invalid input format', 'INVALID_INPUT', 400);
    }

    // Validate configuration key format
    if (!$security->validateConfigKey($configKey)) {
        ResponseHandler::error('Invalid configuration key format', 'INVALID_KEY', 400);
    }

    // Validate game exists
    $gameStmt = $pdo->prepare("SELECT id FROM games WHERE id = ?");
    $gameStmt->execute([$gameId]);
    if (!$gameStmt->fetch()) {
        ResponseHandler::error('Game not found', 'GAME_NOT_FOUND', 404);
    }

    // Determine data type and validate value
    $dataType = determineDataType($input['config_value']);
    if (!$security->validateConfigValue($input['config_value'], $dataType)) {
        ResponseHandler::error('Invalid configuration value for type ' . $dataType, 'INVALID_VALUE', 400);
    }

    // Check if configuration already exists
    $checkStmt = $pdo->prepare("SELECT id FROM configurations WHERE game_id = ? AND config_key = ?");
    $checkStmt->execute([$gameId, $configKey]);
    if ($checkStmt->fetch()) {
        ResponseHandler::error('Configuration key already exists for this game', 'CONFIG_EXISTS', 409);
    }

    // Insert configuration
    $stmt = $pdo->prepare("
        INSERT INTO configurations (game_id, config_key, config_value, data_type, category, description)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $result = $stmt->execute([
        $gameId,
        $configKey,
        json_encode($input['config_value']),
        $dataType,
        $category,
        $description
    ]);

    if (!$result) {
        ResponseHandler::error('Failed to create configuration', 'CREATION_FAILED', 500);
    }

    $configId = $pdo->lastInsertId();

    // Return created configuration
    $config = [
        'id' => $configId,
        'game_id' => $gameId,
        'config_key' => $configKey,
        'config_value' => $input['config_value'],
        'data_type' => $dataType,
        'category' => $category,
        'description' => $description,
        'created_at' => gmdate('c')
    ];

    ResponseHandler::success($config, null, 201);
}

/**
 * Handle PUT request - update configuration
 */
function handleUpdateConfiguration($pdo, $security)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $configId = $_GET['id'] ?? null;

    if (!$input) {
        ResponseHandler::error('Invalid JSON input', 'INVALID_JSON', 400);
    }

    if (empty($configId)) {
        ResponseHandler::error('Configuration ID is required', 'INVALID_INPUT', 400);
    }

    // Validate config ID
    $configId = $security->validateInput($configId, 'int');
    if ($configId === false) {
        ResponseHandler::error('Invalid configuration ID', 'INVALID_INPUT', 400);
    }

    // Check if configuration exists
    $checkStmt = $pdo->prepare("SELECT id FROM configurations WHERE id = ?");
    $checkStmt->execute([$configId]);
    if (!$checkStmt->fetch()) {
        ResponseHandler::error('Configuration not found', 'CONFIG_NOT_FOUND', 404);
    }

    // Build update query
    $updateFields = [];
    $updateValues = [];

    if (isset($input['config_value'])) {
        // Validate config value
        if (!$security->validateConfigValue($input['config_value'], determineDataType($input['config_value']))) {
            ResponseHandler::error('Invalid configuration value', 'INVALID_VALUE', 400);
        }

        $updateFields[] = "config_value = ?";
        $updateValues[] = json_encode($input['config_value']);

        // Update data type based on new value
        $updateFields[] = "data_type = ?";
        $updateValues[] = determineDataType($input['config_value']);
    }

    if (isset($input['category'])) {
        $category = $security->validateInput($input['category'], 'string', 50);
        if ($category === false) {
            ResponseHandler::error('Invalid category format', 'INVALID_INPUT', 400);
        }

        $updateFields[] = "category = ?";
        $updateValues[] = $category;
    }

    if (isset($input['description'])) {
        $description = $security->validateInput($input['description'], 'string', 1000);
        if ($description === false) {
            ResponseHandler::error('Invalid description format', 'INVALID_INPUT', 400);
        }

        $updateFields[] = "description = ?";
        $updateValues[] = $description;
    }

    if (empty($updateFields)) {
        ResponseHandler::error('No fields to update', 'INVALID_INPUT', 400);
    }

    $updateValues[] = $configId;

    $sql = "UPDATE configurations SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($updateValues);

    if (!$result) {
        ResponseHandler::error('Failed to update configuration', 'UPDATE_FAILED', 500);
    }

    ResponseHandler::success(['message' => 'Configuration updated successfully']);
}

/**
 * Handle DELETE request - delete configuration
 */
function handleDeleteConfiguration($pdo, $security)
{
    $configId = $_GET['id'] ?? null;

    if (empty($configId)) {
        ResponseHandler::error('Configuration ID is required', 'INVALID_INPUT', 400);
    }

    // Validate config ID
    $configId = $security->validateInput($configId, 'int');
    if ($configId === false) {
        ResponseHandler::error('Invalid configuration ID', 'INVALID_INPUT', 400);
    }

    // Check if configuration exists
    $checkStmt = $pdo->prepare("SELECT id FROM configurations WHERE id = ?");
    $checkStmt->execute([$configId]);
    if (!$checkStmt->fetch()) {
        ResponseHandler::error('Configuration not found', 'CONFIG_NOT_FOUND', 404);
    }

    // Delete configuration
    $stmt = $pdo->prepare("DELETE FROM configurations WHERE id = ?");
    $result = $stmt->execute([$configId]);

    if (!$result) {
        ResponseHandler::error('Failed to delete configuration', 'DELETE_FAILED', 500);
    }

    ResponseHandler::success(['message' => 'Configuration deleted successfully']);
}
