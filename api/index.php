<?php
declare(strict_types=1);

// Main API endpoint for fetching game configurations

// Output buffering to prevent accidental whitespace injection
ob_start();

// Include config and database connection FIRST to avoid any output before headers
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Initialize database if needed
initializeDatabase();

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Clear buffer before sending headers
    ob_end_clean();
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

// Rate limiting and security checks
$clientIP = getClientIP();

// Check if rate limited
if (isRateLimited($clientIP, API_RATE_WINDOW, API_RATE_LIMIT)) {
    ob_end_clean();
    sendJsonResponse(['error' => 'Rate limit exceeded. Please try again later.'], 429);
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
                ob_end_clean();
                logApiRequest($_SERVER['REQUEST_URI'] ?? 'unknown', ['ip' => $clientIP, 'json_error' => json_last_error_msg()], 400, $clientIP);
                sendJsonResponse(['error' => 'Invalid JSON in request body'], 400);
            }
            if (isset($input['api_key'])) {
                $apiKey = trim($input['api_key']);
            }
        }
    }

    // Enhanced API key validation
    if ($apiKey === null || empty($apiKey)) {
        ob_end_clean();
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $sanitizedUri = preg_replace('/([?&])api_key=[^&]*/', '$1api_key=HIDDEN', $requestUri);
        logApiRequest($sanitizedUri, ['ip' => $clientIP], 401, $clientIP);
        sendJsonResponse(['error' => 'API key required. Use api_key parameter or X-API-Key header.'], 401);
    }

    if (!isValidApiKey($apiKey)) {
        ob_end_clean();
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $sanitizedUri = preg_replace('/([?&])api_key=[^&]*/', '$1api_key=HIDDEN', $requestUri);
        logApiRequest($sanitizedUri, ['ip' => $clientIP, 'reason' => 'invalid_format'], 401, $clientIP);
        sendJsonResponse(['error' => 'Invalid API key format.'], 401);
    }

    // --- File-Based Caching Implementation ---
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0750, true);
        file_put_contents($cacheDir . '/.htaccess', "Require all denied\n");
    }

    $cacheKey = hash('sha256', $apiKey);
    $cacheFile = $cacheDir . '/' . $cacheKey . '.json';
    $cacheDuration = defined('CACHE_DURATION') ? CACHE_DURATION : 300;

    // Check cache
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheDuration)) {
        $cachedContent = file_get_contents($cacheFile);
        $decodedCache = json_decode($cachedContent, true);
        
        if ($decodedCache !== null) {
            ob_end_clean();
            // Add X-Cache header to indicate cache hit
            header('X-Cache: HIT');
            echo $cachedContent;
            exit();
        }
    }

    $result = getGameConfigByApiKey($apiKey);

    if ($result === false) {
        ob_end_clean();
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $sanitizedUri = preg_replace('/([?&])api_key=[^&]*/', '$1api_key=HIDDEN', $requestUri);
        logApiRequest($sanitizedUri, ['ip' => $clientIP, 'error_type' => 'database_error'], 500, $clientIP);
        sendJsonResponse(['error' => 'Service temporarily unavailable'], 500);
    }

    if ($result === null) {
        ob_end_clean();
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $sanitizedUri = preg_replace('/([?&])api_key=[^&]*/', '$1api_key=HIDDEN', $requestUri);
        logApiRequest($sanitizedUri, ['ip' => $clientIP, 'error_type' => 'invalid_api_key'], 401, $clientIP);
        sendJsonResponse(['error' => 'Invalid API key'], 401);
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

    // Save to cache
    $jsonResponse = json_encode($response, JSON_UNESCAPED_UNICODE);
    file_put_contents($cacheFile, $jsonResponse);

    ob_end_clean();
    header('X-Cache: MISS');
    echo $jsonResponse;
    exit();

} catch (Exception $e) {
    ob_end_clean();
    // Don't expose detailed error information in production
    error_log("API Error: " . $e->getMessage());
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $sanitizedUri = preg_replace('/([?&])api_key=[^&]*/', '$1api_key=HIDDEN', $requestUri);
    logApiRequest($sanitizedUri, ['ip' => $clientIP, 'error_type' => 'exception'], 500, $clientIP);
    sendJsonResponse(['error' => 'Service temporarily unavailable'], 500);
}
