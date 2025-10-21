<?php
// Main API endpoint for fetching game configurations

// Include config and database connection FIRST to avoid any output before headers
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Initialize database if needed
initializeDatabase();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
    http_response_code(200);
    exit();
}

// Enhanced security headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Enhanced CSP Header
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'");

// HSTS Header (only in production)
if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'development') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Additional security headers
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
header('Cross-Origin-Embedder-Policy: require-corp');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');

// Enhanced CORS handling
$allowedOrigins = explode(',', ALLOWED_ORIGINS);
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

if ($origin && in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: false');
    header('Vary: Origin');
} else {
    // More restrictive CORS - no wildcard for localhost
    if (in_array($clientIP, ['127.0.0.1', '::1']) && defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        // Only allow specific development origins
        $devOrigins = ['http://localhost:3000', 'http://127.0.0.1:3000', 'https://localhost:3000', 'https://127.0.0.1:3000'];
        if (in_array($origin, $devOrigins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: false');
            header('Vary: Origin');
        } else {
            header('Access-Control-Allow-Origin: ' . ($allowedOrigins[0] ?? 'null'));
        }
    } else {
        header('Access-Control-Allow-Origin: ' . ($allowedOrigins[0] ?? 'null'));
    }
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
header('Access-Control-Allow-Credentials: false'); // Don't allow credentials unless necessary

// Functions moved to api/functions.php to avoid duplication

// Rate limiting and security checks
$clientIP = getClientIP();

// Check if rate limited
if (isRateLimited($clientIP)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
    exit();
}

// Log the API request (without exposing API key in URL)
$requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
$sanitizedUri = preg_replace('/([?&])api_key=[^&]*/', '$1api_key=HIDDEN', $requestUri);
logApiRequest($sanitizedUri, $_GET + $_POST, 200, $clientIP);

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
        $jsonInput = file_get_contents('php://input');
        if (!empty($jsonInput)) {
            $input = json_decode($jsonInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON in request body']);
                logApiRequest($_SERVER['REQUEST_URI'] ?? 'unknown', ['ip' => $clientIP, 'json_error' => json_last_error_msg()], 400, $clientIP);
                exit();
            }
            if (isset($input['api_key'])) {
                $apiKey = trim($input['api_key']);
            }
        }
    }

    // Enhanced API key validation
    if ($apiKey === null || empty($apiKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'API key required. Use api_key parameter or X-API-Key header.']);
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $sanitizedUri = preg_replace('/([?&])api_key=[^&]*/', '$1api_key=HIDDEN', $requestUri);
        logApiRequest($sanitizedUri, ['ip' => $clientIP], 401, $clientIP);
        exit();
    }

    if (!isValidApiKey($apiKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key format.']);
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $sanitizedUri = preg_replace('/([?&])api_key=[^&]*/', '$1api_key=HIDDEN', $requestUri);
        logApiRequest($sanitizedUri, ['ip' => $clientIP, 'reason' => 'invalid_format'], 401, $clientIP);
        exit();
    }

    $result = getGameConfigByApiKey($apiKey);

    if ($result === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Service temporarily unavailable']);
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $sanitizedUri = preg_replace('/([?&])api_key=[^&]*/', '$1api_key=HIDDEN', $requestUri);
        logApiRequest($sanitizedUri, ['ip' => $clientIP, 'error_type' => 'database_error'], 500, $clientIP);
        exit();
    }

    if ($result === null) {
        http_response_code(401); // Use 401 instead of 404 to not reveal if game exists
        echo json_encode(['error' => 'Invalid API key']);
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $sanitizedUri = preg_replace('/([?&])api_key=[^&]*/', '$1api_key=HIDDEN', $requestUri);
        logApiRequest($sanitizedUri, ['ip' => $clientIP, 'error_type' => 'invalid_api_key'], 401, $clientIP);
        exit();
    }

    // Enhanced config processing with security validation
    $processedConfigs = [];
    foreach ($result['configs'] as $key => $value) {
        // Validate config key format - more restrictive pattern
        if (!preg_match('/^[a-zA-Z0-9_\-\.]{1,64}$/', $key)) {
            logSecurityEvent('INVALID_CONFIG_KEY', $clientIP, ['key' => $key]);
            continue; // Skip invalid keys
        }

        // Check value length to prevent DoS
        if (strlen($value) > 10000) {
            logSecurityEvent('CONFIG_VALUE_TOO_LARGE', $clientIP, ['key' => $key, 'length' => strlen($value)]);
            continue;
        }

        // Try to decode the value as JSON to see if it's actually JSON
        $decodedValue = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // If it's valid JSON, store as the actual decoded value
            $processedConfigs[$key] = $decodedValue;
        } else {
            // Enhanced validation for string values
            $sanitizedValue = sanitizeConfigValue($value);

            // Check for potentially dangerous content with more comprehensive patterns
            $dangerousPatterns = [
                '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
                '/<iframe\b[^<]*(?:(?!<\/iframe>)<[^<]*)*<\/iframe>/mi',
                '/<object\b[^<]*(?:(?!<\/object>)<[^<]*)*<\/object>/mi',
                '/<embed\b[^<]*(?:(?!<\/embed>)<[^<]*)*<\/embed>/mi',
                '/javascript:/i',
                '/vbscript:/i',
                '/on\w+\s*=/i',
                '/data:text\/html/i',
                '/data:application\/javascript/i'
            ];

            $isSafe = true;
            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $sanitizedValue)) {
                    $isSafe = false;
                    logSecurityEvent('DANGEROUS_CONFIG_CONTENT', $clientIP, ['key' => $key, 'pattern_matched' => $pattern]);
                    break;
                }
            }

            if ($isSafe) {
                $processedConfigs[$key] = $sanitizedValue;
            }
        }
    }

    // Success response - only return the config data
    $response = [
        'success' => true,
        'config' => $processedConfigs
    ];

    $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $sanitizedUri = preg_replace('/([?&])api_key=[^&]*/', '$1api_key=HIDDEN', $requestUri);
    logApiRequest($sanitizedUri, ['ip' => $clientIP], 200, $clientIP);

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    // Don't expose detailed error information in production
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Service temporarily unavailable']);
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $sanitizedUri = preg_replace('/([?&])api_key=[^&]*/', '$1api_key=HIDDEN', $requestUri);
    logApiRequest($sanitizedUri, ['ip' => $clientIP, 'error_type' => 'exception'], 500, $clientIP);
    exit();
}
