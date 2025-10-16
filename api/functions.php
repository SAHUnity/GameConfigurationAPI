<?php
// Utility functions for the API

// Function to validate input with enhanced security checks
function validateInput($data, $requiredFields = [], $optionalValidation = []) {
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[] = "Required field '$field' is missing or empty";
        }
    }
    
    // Apply additional validation rules if provided
    foreach ($optionalValidation as $field => $rules) {
        if (isset($data[$field]) && !empty($data[$field])) {
            foreach ($rules as $rule => $ruleValue) {
                switch ($rule) {
                    case 'max_length':
                        if (strlen($data[$field]) > $ruleValue) {
                            $errors[] = "Field '$field' exceeds maximum length of $ruleValue characters";
                        }
                        break;
                    case 'min_length':
                        if (strlen($data[$field]) < $ruleValue) {
                            $errors[] = "Field '$field' is shorter than minimum length of $ruleValue characters";
                        }
                        break;
                    case 'type':
                        if (
                            ($ruleValue === 'email' && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) ||
                            ($ruleValue === 'int' && !filter_var($data[$field], FILTER_VALIDATE_INT)) ||
                            ($ruleValue === 'float' && !filter_var($data[$field], FILTER_VALIDATE_FLOAT))
                        ) {
                            $errors[] = "Field '$field' is not a valid $ruleValue";
                        }
                        break;
                    case 'regex':
                        if (!preg_match($ruleValue, $data[$field])) {
                            $errors[] = "Field '$field' does not match required format";
                        }
                        break;
                }
            }
        }
    }
    
    return $errors;
}

// Function to sanitize input with enhanced security
function sanitizeInput($input, $type = 'string') {
    if (is_array($input)) {
        return array_map(function($value) use ($type) {
            return sanitizeInput($value, $type);
        }, $input);
    } else {
        $input = trim($input);
        
        switch ($type) {
            case 'string':
                $input = htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
                break;
            case 'int':
                $input = (int)$input;
                break;
            case 'email':
                $input = filter_var($input, FILTER_SANITIZE_EMAIL);
                break;
            case 'url':
                $input = filter_var($input, FILTER_SANITIZE_URL);
                break;
        }
        
        return $input;
    }
}

// Function to sanitize configuration values specifically (preserving JSON formatting)
function sanitizeConfigValue($value) {
    // Remove any potential script tags but preserve JSON formatting
    $value = strip_tags($value, '<br><br/><p><div><span><strong><em><b><i>'); // Allow some safe HTML tags if needed
    
    // Return without htmlspecialchars to preserve quotes and JSON structure
    return trim($value);
}

// Enhanced function to generate a secure API key
function generateApiKey($length = 32) {
    // Using a more secure random approach
    $bytes = random_bytes(ceil($length / 2));
    return bin2hex(substr($bytes, 0, $length / 2)) . bin2hex(random_bytes(ceil($length / 2)));
}

// Function to get client IP address with more security
function getClientIP() {
    $ipKeys = [
        'HTTP_CF_CONNECTING_IP',    // Cloudflare
        'HTTP_CLIENT_IP',           // Proxy
        'HTTP_X_FORWARDED_FOR',     // Load balancer
        'HTTP_X_FORWARDED',         // Load balancer
        'HTTP_X_CLUSTER_CLIENT_IP', // Cluster
        'HTTP_FORWARDED_FOR',       // Proxy
        'HTTP_FORWARDED',           // Proxy
        'REMOTE_ADDR'               // Standard
    ];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle multiple IPs in the header (comma-separated)
            $ip = explode(',', $ip)[0];
            $ip = trim($ip);
            
            // Validate IP format
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    // Fallback to REMOTE_ADDR if no valid IP found
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Function to log API requests with enhanced security
function logApiRequest($endpoint, $params, $responseCode, $clientIP = null) {
    if ($clientIP === null) {
        $clientIP = getClientIP();
    }
    
    // Sanitize sensitive information from params before logging
    $safeParams = $params;
    if (isset($safeParams['api_key'])) {
        $safeParams['api_key'] = '[HIDDEN]';
    }
    if (isset($safeParams['password'])) {
        $safeParams['password'] = '[HIDDEN]';
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint' => $endpoint,
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'params' => json_encode($safeParams),
        'response_code' => $responseCode,
        'ip_address' => $clientIP,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    // Create a log file in a secure location outside web root if possible, or with restricted access
    $logPath = __DIR__ . '/../logs/api_requests.log';
    $logDir = dirname($logPath);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0750, true);
        // Create .htaccess file to restrict access to logs directory
        $htaccessContent = "Order Deny,Allow\nDeny from all\n";
        file_put_contents($logDir . '/.htaccess', $htaccessContent);
    }
    
    error_log('[' . date('Y-m-d H:i:s') . '] ' . json_encode($logEntry) . "\n", 3, $logPath);
    
    // Also log to system log for additional security
    error_log('API_ACCESS: ' . $clientIP . ' - ' . $_SERVER['REQUEST_METHOD'] . ' ' . $endpoint . ' - Response: ' . $responseCode, 0);
}

// Function to send a proper JSON response
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data);
    exit();
}

// Function to check rate limiting
function isRateLimited($clientIP, $timeWindow = 300, $maxRequests = 60) {
    // Create a simple rate limiting based on IP
    $rateLimitDir = __DIR__ . '/../rate_limit';
    if (!file_exists($rateLimitDir)) {
        mkdir($rateLimitDir, 0750, true);
        // Create .htaccess file to restrict access to rate_limit directory
        $htaccessContent = "Order Deny,Allow\nDeny from all\n";
        file_put_contents($rateLimitDir . '/.htaccess', $htaccessContent);
    }
    
    $rateLimitFile = $rateLimitDir . '/' . hash('sha256', $clientIP) . '.json';
    
    if (file_exists($rateLimitFile)) {
        $rateData = json_decode(file_get_contents($rateLimitFile), true);
        if ($rateData && $rateData['timestamp'] > (time() - $timeWindow)) {
            if ($rateData['count'] >= $maxRequests) {
                // Log rate limit exceeded event
                logSecurityEvent('RATE_LIMIT_EXCEEDED', $clientIP);
                return true; // Rate limit exceeded
            } else {
                // Increment count
                $rateData['count']++;
                file_put_contents($rateLimitFile, json_encode($rateData));
            }
        } else {
            // Reset with new window
            $rateData = [
                'timestamp' => time(),
                'count' => 1
            ];
            file_put_contents($rateLimitFile, json_encode($rateData));
        }
    } else {
        // Create first entry
        $rateData = [
            'timestamp' => time(),
            'count' => 1
        ];
        file_put_contents($rateLimitFile, json_encode($rateData));
    }
    
    return false; // Not rate limited
}

// Function to validate API key format
function isValidApiKey($apiKey) {
    // API keys should be of a specific length and format
    return (bool) preg_match('/^[a-zA-Z0-9]{16,64}$/', $apiKey);
}

// Function to log security events
function logSecurityEvent($event, $clientIP = null, $details = []) {
    if ($clientIP === null) {
        $clientIP = getClientIP();
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip_address' => $clientIP,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details,
        'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ];
    
    $logPath = __DIR__ . '/../logs/security_events.log';
    error_log('[' . date('Y-m-d H:i:s') . '] ' . json_encode($logEntry) . "\n", 3, $logPath);
}

// Function to validate HTTP method
function isValidHttpMethod($method) {
    $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];
    return in_array(strtoupper($method), $validMethods);
}

// Function to start secure session
function startSecureSession() {
    // Set session cookie parameters for security
    $session_name = 'GAME_CONFIG_SESSION';
    session_name($session_name);
    
    // Prevent JavaScript access to session cookie
    $secure = false; // Set to true if using HTTPS
    $httponly = true; // Prevents JavaScript access to session cookie
    $samesite = 'Strict'; // CSRF protection
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $secure = true;
    }
    
    // Set the session cookie with security parameters
    if (PHP_VERSION_ID < 70300) {
        session_set_cookie_params(3600, '/', '', $secure, $httponly); // 1 hour timeout
    } else {
        session_set_cookie_params([
            'lifetime' => 3600, // 1 hour
            'path' => '/',
            'domain' => '',
            'secure' => $secure,  // Only send over HTTPS in production
            'httponly' => $httponly, // Prevents JavaScript access
            'samesite' => $samesite // CSRF protection
        ]);
    }
    
    // Start the session
    session_start();
    
    // Check if session has been hijacked
    if (isset($_SESSION['last_ip']) && $_SESSION['last_ip'] !== getClientIP()) {
        session_destroy();
        header('Location: ../admin/login.php');
        exit();
    }
    
    if (isset($_SESSION['last_user_agent']) && $_SESSION['last_user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_destroy();
        header('Location: ../admin/login.php');
        exit();
    }
    
    // Update session timestamp and IP
    $_SESSION['last_activity'] = time();
    $_SESSION['last_ip'] = getClientIP();
    $_SESSION['last_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // Every 30 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// Function to check if session is valid and not timed out
function isSessionValid() {
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }
    
    // Session timeout after 1 hour of inactivity
    if (time() - $_SESSION['last_activity'] > 3600) {
        session_destroy();
        return false;
    }
    
    return true;
}