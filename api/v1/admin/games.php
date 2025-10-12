<?php

/**
 * Admin Games Management API endpoints
 */

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            handleGetGames($pdo);
            break;
        case 'POST':
            handleCreateGame($pdo, $auth);
            break;
        case 'PUT':
            handleUpdateGame($pdo);
            break;
        case 'DELETE':
            handleDeleteGame($pdo);
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
function handleCreateGame($pdo, $auth)
{
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    if (empty($input['name']) || empty($input['game_id'])) {
        ResponseHandler::error('Name and game_id are required', 'INVALID_INPUT', 400);
    }

    // Check if game_id already exists
    $checkStmt = $pdo->prepare("SELECT id FROM games WHERE game_id = ?");
    $checkStmt->execute([$input['game_id']]);
    if ($checkStmt->fetch()) {
        ResponseHandler::error('Game ID already exists', 'GAME_ID_EXISTS', 409);
    }

    // Generate API key
    $apiKey = $auth->generateApiKey();
    $hashedApiKey = hash('sha256', $apiKey);

    // Insert new game
    $stmt = $pdo->prepare("
        INSERT INTO games (name, game_id, api_key, description, status) 
        VALUES (?, ?, ?, ?, ?)
    ");

    $result = $stmt->execute([
        $input['name'],
        $input['game_id'],
        $hashedApiKey,
        $input['description'] ?? null,
        $input['status'] ?? 'active'
    ]);

    if (!$result) {
        ResponseHandler::error('Failed to create game', 'CREATION_FAILED', 500);
    }

    $gameId = $pdo->lastInsertId();

    // Return created game with API key (only time it's shown)
    $game = [
        'id' => $gameId,
        'name' => $input['name'],
        'game_id' => $input['game_id'],
        'api_key' => $apiKey, // Return unhashed key for admin
        'description' => $input['description'] ?? null,
        'status' => $input['status'] ?? 'active',
        'created_at' => gmdate('c')
    ];

    ResponseHandler::success($game, null, 201);
}

/**
 * Handle PUT request - update game
 */
function handleUpdateGame($pdo)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $gameId = $_GET['id'] ?? null;

    if (empty($gameId)) {
        ResponseHandler::error('Game ID is required', 'INVALID_INPUT', 400);
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
        $updateFields[] = "name = ?";
        $updateValues[] = $input['name'];
    }

    if (isset($input['description'])) {
        $updateFields[] = "description = ?";
        $updateValues[] = $input['description'];
    }

    if (isset($input['status'])) {
        $updateFields[] = "status = ?";
        $updateValues[] = $input['status'];
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
function handleDeleteGame($pdo)
{
    $gameId = $_GET['id'] ?? null;

    if (empty($gameId)) {
        ResponseHandler::error('Game ID is required', 'INVALID_INPUT', 400);
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
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM configurations WHERE game_id = ?");
    $stmt->execute([$gameId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)$result['count'];
}
