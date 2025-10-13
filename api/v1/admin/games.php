<?php

/**
 * Admin Games Management API endpoints
 */

// Initialize security middleware
$security = new SecurityMiddleware($pdo);

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetGames($pdo);
            break;
        case 'POST':
            handleCreateGame($pdo, $auth, $security);
            break;
        case 'PUT':
            handleUpdateGame($pdo, $security);
            break;
        case 'DELETE':
            handleDeleteGame($pdo, $security);
            break;
        default:
            ResponseHandler::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    }
} catch (Exception $e) {
    error_log("Admin Games API Error: " . $e->getMessage());
    ResponseHandler::error('Internal server error', 'INTERNAL_ERROR', 500);
}

/**
 * Handle GET request - list all games
 */
function handleGetGames($pdo)
{
    $stmt = $pdo->prepare("
        SELECT id, name, game_id, description, status, created_at, updated_at 
        FROM games 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Don't expose API keys in the list
    foreach ($games as &$game) {
        $game['config_count'] = getConfigCount($pdo, $game['id']);
    }

    ResponseHandler::success($games);
}

/**
 * Handle POST request - create new game
 */
function handleCreateGame($pdo, $auth, $security)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        ResponseHandler::error('Invalid JSON input', 'INVALID_JSON', 400);
    }

    // Validate required fields
    if (empty($input['name']) || empty($input['game_id'])) {
        ResponseHandler::error('Name and game_id are required', 'INVALID_INPUT', 400);
    }

    // Validate and sanitize inputs
    $name = $security->validateInput($input['name'], 'string', 100);
    $gameId = $security->validateInput($input['game_id'], 'alphanumeric', 50);
    $description = $security->validateInput($input['description'] ?? null, 'string', 1000);
    $status = $security->validateInput($input['status'] ?? 'active', 'string', 20);

    if ($name === false || $gameId === false || $description === false || $status === false) {
        ResponseHandler::error('Invalid input format', 'INVALID_INPUT', 400);
    }

    // Validate status value
    if (!in_array($status, ['active', 'inactive'])) {
        ResponseHandler::error('Invalid status value', 'INVALID_STATUS', 400);
    }

    // Check if game_id already exists
    $checkStmt = $pdo->prepare("SELECT id FROM games WHERE game_id = ?");
    $checkStmt->execute([$gameId]);
    if ($checkStmt->fetch()) {
        ResponseHandler::error('Game ID already exists', 'GAME_ID_EXISTS', 409);
    }

    // Generate API key
    $apiKey = $auth->generateApiKey();
    $hashedApiKey = hash('sha384', $apiKey) . hash('ripemd160', $apiKey);

    // Insert new game
    $stmt = $pdo->prepare("
        INSERT INTO games (name, game_id, api_key, description, status)
        VALUES (?, ?, ?, ?, ?)
    ");

    $result = $stmt->execute([
        $name,
        $gameId,
        $hashedApiKey,
        $description,
        $status
    ]);

    if (!$result) {
        ResponseHandler::error('Failed to create game', 'CREATION_FAILED', 500);
    }

    $gameDbId = $pdo->lastInsertId();

    // Return created game with API key (only time it's shown)
    $game = [
        'id' => $gameDbId,
        'name' => $name,
        'game_id' => $gameId,
        'api_key' => $apiKey, // Return unhashed key for admin
        'description' => $description,
        'status' => $status,
        'created_at' => gmdate('c')
    ];

    ResponseHandler::success($game, null, 201);
}

/**
 * Handle PUT request - update game
 */
function handleUpdateGame($pdo, $security)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $gameId = $_GET['id'] ?? null;

    if (!$input) {
        ResponseHandler::error('Invalid JSON input', 'INVALID_JSON', 400);
    }

    if (empty($gameId)) {
        ResponseHandler::error('Game ID is required', 'INVALID_INPUT', 400);
    }

    // Validate game ID
    $gameId = $security->validateInput($gameId, 'int');
    if ($gameId === false) {
        ResponseHandler::error('Invalid game ID', 'INVALID_INPUT', 400);
    }

    // Check if game exists
    $checkStmt = $pdo->prepare("SELECT id FROM games WHERE id = ?");
    $checkStmt->execute([$gameId]);
    if (!$checkStmt->fetch()) {
        ResponseHandler::error('Game not found', 'GAME_NOT_FOUND', 404);
    }

    // Build update query
    $updateFields = [];
    $updateValues = [];

    if (isset($input['name'])) {
        $name = $security->validateInput($input['name'], 'string', 100);
        if ($name === false) {
            ResponseHandler::error('Invalid name format', 'INVALID_INPUT', 400);
        }
        $updateFields[] = "name = ?";
        $updateValues[] = $name;
    }

    if (isset($input['description'])) {
        $description = $security->validateInput($input['description'], 'string', 1000);
        if ($description === false) {
            ResponseHandler::error('Invalid description format', 'INVALID_INPUT', 400);
        }
        $updateFields[] = "description = ?";
        $updateValues[] = $description;
    }

    if (isset($input['status'])) {
        $status = $security->validateInput($input['status'], 'string', 20);
        if ($status === false || !in_array($status, ['active', 'inactive'])) {
            ResponseHandler::error('Invalid status value', 'INVALID_STATUS', 400);
        }
        $updateFields[] = "status = ?";
        $updateValues[] = $status;
    }

    if (empty($updateFields)) {
        ResponseHandler::error('No fields to update', 'INVALID_INPUT', 400);
    }

    $updateValues[] = $gameId;

    $sql = "UPDATE games SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($updateValues);

    if (!$result) {
        ResponseHandler::error('Failed to update game', 'UPDATE_FAILED', 500);
    }

    ResponseHandler::success(['message' => 'Game updated successfully']);
}

/**
 * Handle DELETE request - delete game
 */
function handleDeleteGame($pdo, $security)
{
    $gameId = $_GET['id'] ?? null;

    if (empty($gameId)) {
        ResponseHandler::error('Game ID is required', 'INVALID_INPUT', 400);
    }

    // Validate game ID
    $gameId = $security->validateInput($gameId, 'int');
    if ($gameId === false) {
        ResponseHandler::error('Invalid game ID', 'INVALID_INPUT', 400);
    }

    // Check if game exists
    $checkStmt = $pdo->prepare("SELECT id FROM games WHERE id = ?");
    $checkStmt->execute([$gameId]);
    if (!$checkStmt->fetch()) {
        ResponseHandler::error('Game not found', 'GAME_NOT_FOUND', 404);
    }

    // Delete game (configurations will be deleted due to foreign key constraint)
    $stmt = $pdo->prepare("DELETE FROM games WHERE id = ?");
    $result = $stmt->execute([$gameId]);

    if (!$result) {
        ResponseHandler::error('Failed to delete game', 'DELETE_FAILED', 500);
    }

    ResponseHandler::success(['message' => 'Game deleted successfully']);
}

/**
 * Get configuration count for a game
 */
function getConfigCount($pdo, $gameId)
{
    static $stmt = null;

    if ($stmt === null) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM configurations WHERE game_id = ?");
    }

    $stmt->execute([$gameId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)$result['count'];
}
