<?php
// Main API endpoint for fetching game configurations
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust as needed for security
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Include config and database connection
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/config.php';

// Initialize database if needed
initializeDatabase();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Function to get game configuration
function getGameConfig($gameId, $gameSlug = null) {
    $pdo = getDBConnection();
    
    try {
        // If we have a slug, get the game ID first
        if ($gameId === null && $gameSlug !== null) {
            $stmt = $pdo->prepare("SELECT id FROM games WHERE slug = ? AND is_active = 1");
            $stmt->execute([$gameSlug]);
            $result = $stmt->fetch();
            if (!$result) {
                return null;
            }
            $gameId = $result['id'];
        }
        
        // Get active configurations for the game (ensure game is also active)
        $stmt = $pdo->prepare("
            SELECT c.config_key, c.config_value 
            FROM configurations c
            JOIN games g ON c.game_id = g.id
            WHERE c.game_id = ? AND c.is_active = 1 AND g.is_active = 1
            ORDER BY c.config_key
        ");
        $stmt->execute([$gameId]);
        
        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return $configs;
    } catch (PDOException $e) {
        error_log("Database error in getGameConfig: " . $e->getMessage());
        return false;
    }
}

// Main API logic
try {
    $gameId = null;
    $gameSlug = null;
    
    // Determine how the game is identified
    if (isset($_GET['game_id']) && is_numeric($_GET['game_id'])) {
        $gameId = (int)$_GET['game_id'];
    } elseif (isset($_GET['slug'])) {
        $gameSlug = trim($_GET['slug']);
        // Validate slug format - only alphanumeric, hyphens, and underscores
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $gameSlug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid slug format']);
            exit();
        }
    } elseif (isset($_POST['game_id']) && is_numeric($_POST['game_id'])) {
        $gameId = (int)$_POST['game_id'];
    } elseif (isset($_POST['slug'])) {
        $gameSlug = trim($_POST['slug']);
        // Validate slug format - only alphanumeric, hyphens, and underscores
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $gameSlug)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid slug format']);
            exit();
        }
    } else {
        // Check for game identifier in request body (for POST requests with JSON)
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['game_id']) && is_numeric($input['game_id'])) {
            $gameId = (int)$input['game_id'];
        } elseif (isset($input['slug'])) {
            $gameSlug = trim($input['slug']);
            // Validate slug format - only alphanumeric, hyphens, and underscores
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $gameSlug)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid slug format']);
                exit();
            }
        }
    }
    
    if ($gameId === null && $gameSlug === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing game identifier. Use game_id or slug parameter.']);
        exit();
    }
    
    $config = getGameConfig($gameId, $gameSlug);
    
    if ($config === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error occurred']);
        exit();
    }
    
    if ($config === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Game not found']);
        exit();
    }
    
    // Get the game slug if it wasn't provided but game_id was
    if ($gameSlug === null && $gameId !== null) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT slug FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
        $game = $stmt->fetch();
        $gameSlug = $game ? $game['slug'] : null;
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'game_id' => $gameId,
        'slug' => $gameSlug,
        'config' => $config
    ]);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred']);
    exit();
}