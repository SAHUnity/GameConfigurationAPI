<?php

/**
 * API Response Handler for Game Configuration API
 */
class ResponseHandler
{
    /**
     * Send success response
     * @param mixed $data Response data
     * @param array $meta Additional metadata
     */
    public static function success($data = null, $meta = [])
    {
        $response = [
            'success' => true,
            'data' => $data,
            'meta' => array_merge([
                'version' => APP_VERSION,
                'timestamp' => gmdate('c')
            ], $meta)
        ];

        self::sendResponse($response, 200);
    }

    /**
     * Send error response
     * @param string $message Error message
     * @param string $code Error code
     * @param int $httpStatus HTTP status code
     */
    public static function error($message, $code = 'INTERNAL_ERROR', $httpStatus = 500)
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ],
            'meta' => [
                'timestamp' => gmdate('c')
            ]
        ];

        self::sendResponse($response, $httpStatus);
    }

    /**
     * Send JSON response and exit
     * @param array $response Response data
     * @param int $httpStatus HTTP status code
     */
    private static function sendResponse($response, $httpStatus)
    {
        // Set headers
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: ' . CORS_ALLOWED_ORIGINS);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        http_response_code($httpStatus);
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
