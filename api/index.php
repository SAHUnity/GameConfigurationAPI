<?php
// Main API endpoint for fetching game configurations
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Adjust as needed for security
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

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

// Function to get game configuration by API key
function getGameConfigByApiKey($apiKey) {
    $pdo = getDBConnection();
    
    try {
        // Get active configurations for the game (ensure game is also active)
        $stmt = $pdo->prepare("
            SELECT c.config_key, c.config_value
            FROM configurations c
            JOIN games g ON c.game_id = g.id
            WHERE g.api_key = ? AND c.is_active = 1 AND g.is_active = 1
            ORDER BY c.config_key
        ");
        $stmt->execute([$apiKey]);
        
        $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (empty($configs)) {
            return null;
        }
        
        return [
            'configs' => $configs
        ];
    } catch (PDOException $e) {
        error_log("Database error in getGameConfigByApiKey: " . $e->getMessage());
        return false;
    }
}

// Main API logic
try {
    $apiKey = null;
    
    // Determine how the API key is provided
    if (isset($_GET['api_key'])) {
        $apiKey = trim($_GET['api_key']);
    } elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
        $apiKey = trim($_SERVER['HTTP_X_API_KEY']);
    } elseif (isset($_POST['api_key'])) {
        $apiKey = trim($_POST['api_key']);
    } else {
        // Check for API key in request body (for POST requests with JSON)
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['api_key'])) {
            $apiKey = trim($input['api_key']);
        }
    }
    
    if ($apiKey === null || empty($apiKey)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing API key. Use api_key parameter or X-API-Key header.']);
        exit();
    }
    
    $result = getGameConfigByApiKey($apiKey);
    
    if ($result === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error occurred']);
        exit();
    }
    
    if ($result === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Game not found or API key is invalid']);
        exit();
    }
    
    // Success response - only return the config data
    echo json_encode([
        'success' => true,
        'config' => $result['configs']
    ]);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred']);
    exit();
}