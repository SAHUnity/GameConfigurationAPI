<?php

/**
 * Security middleware for Game Configuration API
 */
class SecurityMiddleware
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check for brute force attempts
     * @param string $identifier IP address or username
     * @param int $maxAttempts Maximum allowed attempts
     * @param int $lockoutTime Lockout time in seconds
     * @return array [allowed, remainingAttempts, lockoutTime]
     */
    public function checkBruteForce($identifier, $maxAttempts = 5, $lockoutTime = 900)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempts, 
                   MAX(created_at) as last_attempt,
                   MAX(CASE WHEN success = 1 THEN created_at END) as last_success
            FROM login_attempts 
            WHERE identifier = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$identifier, $lockoutTime]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $attempts = (int)$result['attempts'];
        $lastAttempt = $result['last_attempt'];
        $lastSuccess = $result['last_success'];

        // Reset count after successful login
        if ($lastSuccess && strtotime($lastSuccess) > strtotime($lastAttempt)) {
            return [true, $maxAttempts, 0];
        }

        $remainingAttempts = max(0, $maxAttempts - $attempts);
        $isLocked = $attempts >= $maxAttempts;

        if ($isLocked && $lastAttempt) {
            $lockoutRemaining = $lockoutTime - (time() - strtotime($lastAttempt));
            return [false, 0, max(0, $lockoutRemaining)];
        }

        return [!$isLocked, $remainingAttempts, 0];
    }

    /**
     * Record a login attempt
     * @param string $identifier IP address or username
     * @param bool $success Whether the login was successful
     * @param string $username Username attempted
     */
    public function recordLoginAttempt($identifier, $success, $username = '')
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (identifier, success, username, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $identifier,
            $success ? 1 : 0,
            $username,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        // Clean old attempts
        $this->cleanupOldAttempts();
    }

    /**
     * Clean up old login attempts
     */
    private function cleanupOldAttempts()
    {
        // Only cleanup occasionally to avoid performance impact
        if (rand(1, 100) === 1) {
            $stmt = $this->pdo->prepare("
                DELETE FROM login_attempts 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute();
        }
    }

    /**
     * Validate and sanitize input
     * @param mixed $input Input to validate
     * @param string $type Type of validation (string, int, float, bool, email, json)
     * @param int $maxLength Maximum length for strings
     * @return mixed Sanitized input or false if invalid
     */
    public function validateInput($input, $type = 'string', $maxLength = 1000)
    {
        if ($input === null) {
            return null;
        }

        switch ($type) {
            case 'string':
                if (!is_string($input)) {
                    return false;
                }
                $input = trim($input);
                if (strlen($input) > $maxLength) {
                    return false;
                }
                return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

            case 'int':
                if (!is_numeric($input)) {
                    return false;
                }
                return (int)$input;

            case 'float':
                if (!is_numeric($input)) {
                    return false;
                }
                return (float)$input;

            case 'bool':
                if (is_bool($input)) {
                    return $input;
                }
                if ($input === 'true' || $input === '1') {
                    return true;
                }
                if ($input === 'false' || $input === '0') {
                    return false;
                }
                return false;

            case 'email':
                if (!is_string($input)) {
                    return false;
                }
                $input = trim($input);
                if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
                return $input;

            case 'json':
                if (!is_string($input)) {
                    return false;
                }
                if (strlen($input) > 65535) { // 64KB max
                    return false;
                }
                json_decode($input);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return false;
                }
                return $input;

            case 'alphanumeric':
                if (!is_string($input)) {
                    return false;
                }
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $input)) {
                    return false;
                }
                return $input;

            default:
                return false;
        }
    }

    /**
     * Validate configuration key
     * @param string $key Configuration key to validate
     * @return bool
     */
    public function validateConfigKey($key)
    {
        if (!is_string($key) || empty($key)) {
            return false;
        }

        // Allow alphanumeric, dots, underscores, and hyphens
        // Max length of 255 characters
        if (strlen($key) > 255) {
            return false;
        }

        return preg_match('/^[a-zA-Z0-9._-]+$/', $key) === 1;
    }

    /**
     * Validate configuration value
     * @param mixed $value Configuration value to validate
     * @param string $type Expected data type
     * @return bool
     */
    public function validateConfigValue($value, $type)
    {
        switch ($type) {
            case 'string':
                return is_string($value) && strlen($value) <= 65535;

            case 'number':
                return is_numeric($value);

            case 'boolean':
                return is_bool($value);

            case 'array':
                return is_array($value) && count($value) <= 1000; // Limit array size

            case 'object':
                return is_object($value) || (is_array($value) && count($value) <= 100);

            default:
                return false;
        }
    }
}
