<?php

/**
 * Admin Configuration Management API endpoints
 */

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetConfigurations($pdo);
            break;
        case 'POST':
            handleCreateConfiguration($pdo);
            break;
        case 'PUT':
            handleUpdateConfiguration($pdo);
            break;
        case 'DELETE':
            handleDeleteConfiguration($pdo);
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
function handleGetConfigurations($pdo)
{
    $gameId = $_GET['game_id'] ?? null;
    $category = $_GET['category'] ?? null;

    $sql = "
        SELECT c.*, g.name as game_name, g.game_id 
        FROM configurations c 
        JOIN games g ON c.game_id = g.id
        WHERE 1=1
    ";
    $params = [];

    if ($gameId) {
        $sql .= " AND c.game_id = ?";
        $params[] = $gameId;
    }

    if ($category) {
        $sql .= " AND c.category = ?";
        $params[] = $category;
    }

    $sql .= " ORDER BY c.category, c.config_key";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON values for display
    foreach ($configs as &$config) {
        $config['config_value'] = json_decode($config['config_value'], true);
    }

    ResponseHandler::success($configs);
}

/**
 * Handle POST request - create new configuration
 */
function handleCreateConfiguration($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($input['game_id']) || empty($input['config_key']) || !isset($input['config_value'])) {
        ResponseHandler::error('game_id, config_key, and config_value are required', 'INVALID_INPUT', 400);
    }

    // Validate game exists
    $gameStmt = $pdo->prepare("SELECT id FROM games WHERE id = ?");
    $gameStmt->execute([$input['game_id']]);
    if (!$gameStmt->fetch()) {
        ResponseHandler::error('Game not found', 'GAME_NOT_FOUND', 404);
    }

    // Determine data type
    $dataType = determineDataType($input['config_value']);

    // Check if configuration already exists
    $checkStmt = $pdo->prepare("SELECT id FROM configurations WHERE game_id = ? AND config_key = ?");
    $checkStmt->execute([$input['game_id'], $input['config_key']]);
    if ($checkStmt->fetch()) {
        ResponseHandler::error('Configuration key already exists for this game', 'CONFIG_EXISTS', 409);
    }

    // Insert configuration
    $stmt = $pdo->prepare("
        INSERT INTO configurations (game_id, config_key, config_value, data_type, category, description) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $result = $stmt->execute([
        $input['game_id'],
        $input['config_key'],
        json_encode($input['config_value']),
        $dataType,
        $input['category'] ?? 'general',
        $input['description'] ?? null
    ]);

    if (!$result) {
        ResponseHandler::error('Failed to create configuration', 'CREATION_FAILED', 500);
    }

    $configId = $pdo->lastInsertId();

    // Return created configuration
    $config = [
        'id' => $configId,
        'game_id' => $input['game_id'],
        'config_key' => $input['config_key'],
        'config_value' => $input['config_value'],
        'data_type' => $dataType,
        'category' => $input['category'] ?? 'general',
        'description' => $input['description'] ?? null,
        'created_at' => gmdate('c')
    ];

    ResponseHandler::success($config, null, 201);
}

/**
 * Handle PUT request - update configuration
 */
function handleUpdateConfiguration($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $configId = $_GET['id'] ?? null;

    if (empty($configId)) {
        ResponseHandler::error('Configuration ID is required', 'INVALID_INPUT', 400);
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
        $updateFields[] = "config_value = ?";
        $updateValues[] = json_encode($input['config_value']);

        // Update data type based on new value
        $updateFields[] = "data_type = ?";
        $updateValues[] = determineDataType($input['config_value']);
    }

    if (isset($input['category'])) {
        $updateFields[] = "category = ?";
        $updateValues[] = $input['category'];
    }

    if (isset($input['description'])) {
        $updateFields[] = "description = ?";
        $updateValues[] = $input['description'];
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
function handleDeleteConfiguration($pdo)
{
    $configId = $_GET['id'] ?? null;

    if (empty($configId)) {
        ResponseHandler::error('Configuration ID is required', 'INVALID_INPUT', 400);
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

/**
 * Determine data type of value
 */
function determineDataType($value)
{
    if (is_bool($value)) {
        return 'boolean';
    } elseif (is_numeric($value)) {
        return is_float($value) ? 'float' : 'number';
    } elseif (is_array($value)) {
        return 'array';
    } elseif (is_object($value)) {
        return 'object';
    } else {
        return 'string';
    }
}
