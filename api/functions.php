<?php
// Utility functions for the API

// Function to validate input
function validateInput($data, $requiredFields = []) {
    $errors = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[] = "Required field '$field' is missing or empty";
        }
    }
    
    return $errors;
}

// Function to sanitize input
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    } else {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
}

// Function to generate a secure API key
function generateApiKey($length = 32) {
    // Using a combination of letters, numbers, and symbols for security
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
    $key = '';
    $charLength = strlen($characters);
    
    for ($i = 0; $i < $length; $i++) {
        $key .= $characters[random_int(0, $charLength - 1)];
    }
    
    return $key;
}

// Function to log API requests (optional)
function logApiRequest($endpoint, $params, $responseCode) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'endpoint' => $endpoint,
        'params' => json_encode($params),
        'response_code' => $responseCode,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    // You could write this to a file or database
    error_log(json_encode($logEntry));
}

// Function to send a proper JSON response
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}