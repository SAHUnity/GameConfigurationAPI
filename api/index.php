<?php
// Main API endpoint for fetching game configurations
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Handle CORS
$allowedOrigins = explode(',', ALLOWED_ORIGINS);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // For development, you might want to allow all origins, but in production, be specific
    header('Access-Control-Allow-Origin: ' . (in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']) ? '*' : $allowedOrigins[0] ?? 'null'));
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
header('Access-Control-Allow-Credentials: false'); // Don't allow credentials unless necessary

// Include config and database connection
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

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
        // Use a more secure comparison by hashing the API key or using a different approach
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

// Function to verify API key existence without revealing if it's valid
function verifyApiKey($apiKey) {
    $pdo = getDBConnection();
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM games WHERE api_key = ? AND is_active = 1");
        $stmt->execute([$apiKey]);
        $count = $stmt->fetchColumn();
        
        return $count > 0;
    } catch (PDOException $e) {
        error_log("API key verification error: " . $e->getMessage());
        return false;
    }
}

// Rate limiting and security checks
$clientIP = getClientIP();

// Check if rate limited
if (isRateLimited($clientIP)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
    exit();
}

// Log the API request
logApiRequest($_SERVER['REQUEST_URI'] ?? 'unknown', $_GET + $_POST, 200, $clientIP);

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
    
    // Validate API key format
    if ($apiKey === null || empty($apiKey) || !isValidApiKey($apiKey)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing or invalid API key format. Use api_key parameter or X-API-Key header.']);
        logApiRequest($_SERVER['REQUEST_URI'] ?? 'unknown', ['ip' => $clientIP], 400, $clientIP);
        exit();
    }
    
    $result = getGameConfigByApiKey($apiKey);
    
    if ($result === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error occurred']);
        logApiRequest($_SERVER['REQUEST_URI'] ?? 'unknown', ['ip' => $clientIP], 500, $clientIP);
        exit();
    }
    
    if ($result === null) {
        http_response_code(401); // Use 401 instead of 404 to not reveal if game exists
        echo json_encode(['error' => 'Unauthorized: Invalid API key']);
        logApiRequest($_SERVER['REQUEST_URI'] ?? 'unknown', ['ip' => $clientIP], 401, $clientIP);
        exit();
    }
    
    // Success response - only return the config data
    echo json_encode([
        'success' => true,
        'config' => $result['configs']
    ]);
    
    logApiRequest($_SERVER['REQUEST_URI'] ?? 'unknown', ['ip' => $clientIP], 200, $clientIP);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred']);
    logApiRequest($_SERVER['REQUEST_URI'] ?? 'unknown', ['ip' => $clientIP, 'error' => $e->getMessage()], 500, $clientIP);
    exit();
}