<?php

/**
 * Authentication class for Game Configuration API
 */
class Auth
{
    private $pdo;
    private $security;

    /**
     * Constructor
     * @param PDO $pdo Database connection
     * @param SecurityMiddleware $security Optional security middleware instance
     */
    public function __construct($pdo, $security = null)
    {
        $this->pdo = $pdo;
        $this->security = $security ?: new SecurityMiddleware($pdo);
    }

    /**
     * Validate API key
     * @param string $apiKey API key to validate
     * @return array|false Game data if valid, false otherwise
     */
    public function validateApiKey($apiKey)
    {
        if (empty($apiKey)) {
            return false;
        }

        // Validate API key format
        if (!$this->security->validateInput($apiKey, 'alphanumeric', 128)) {
            return false;
        }

        // Use multiple hashing methods for better security
        $hashedKey = hash('sha384', $apiKey);
        $hashedKey .= hash('ripemd160', $apiKey);

        try {
            $stmt = $this->pdo->prepare("SELECT id, game_id FROM games WHERE api_key = ? AND status = 'active'");
            $stmt->execute([$hashedKey]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("API key validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate admin session
     * @return bool True if session is valid
     */
    public function validateAdminSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['admin_id'])) {
            return false;
        }

        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            $this->logoutAdmin();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Login admin user with brute force protection
     * @param string $username Username
     * @param string $password Password
     * @return array [success, message, remainingAttempts, lockoutTime]
     */
    public function loginAdmin($username, $password)
    {
        if (empty($username) || empty($password)) {
            return [false, 'Username and password are required', 0, 0];
        }

        // Validate inputs
        $username = $this->security->validateInput($username, 'string', 50);
        if (!$username) {
            return [false, 'Invalid username format', 0, 0];
        }

        $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Check brute force protection
        list($allowed, $remainingAttempts, $lockoutTime) = $this->security->checkBruteForce($identifier);

        if (!$allowed) {
            $this->security->recordLoginAttempt($identifier, false, $username);
            return [false, 'Too many failed attempts. Please try again later.', 0, $lockoutTime];
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, password FROM admin_users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                $this->security->recordLoginAttempt($identifier, true, $username);

                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }

                $_SESSION['admin_id'] = $user['id'];
                $_SESSION['last_activity'] = time();

                // Update last login
                $updateStmt = $this->pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);

                return [true, 'Login successful', 5, 0];
            } else {
                // Failed login
                $this->security->recordLoginAttempt($identifier, false, $username);
                $remainingAttempts = max(0, $remainingAttempts - 1);
                return [false, 'Invalid username or password', $remainingAttempts, 0];
            }
        } catch (PDOException $e) {
            error_log("Admin login error: " . $e->getMessage());
            return [false, 'Login system error', 0, 0];
        }
    }

    /**
     * Logout admin user
     */
    public function logoutAdmin()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }

    /**
     * Generate secure API key with better entropy
     * @return string Generated API key
     */
    public function generateApiKey()
    {
        // Generate a more secure API key with timestamp and entropy
        $entropy = random_bytes(32);
        $timestamp = microtime(true);
        $serverId = substr(md5(__FILE__), 0, 8);

        $key = bin2hex($entropy) . dechex($timestamp) . $serverId;
        return substr($key, 0, 64); // Ensure consistent length
    }

    /**
     * Create admin user
     * @param string $username Username
     * @param string $password Password
     * @param string $email Email
     * @return bool True if creation successful
     */
    public function createAdminUser($username, $password, $email = null)
    {
        if (empty($username) || empty($password)) {
            return false;
        }

        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("INSERT INTO admin_users (username, password, email) VALUES (?, ?, ?)");
            return $stmt->execute([$username, $hashedPassword, $email]);
        } catch (PDOException $e) {
            error_log("Admin user creation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current admin user data
     * @return array|false Admin data or false
     */
    public function getCurrentAdmin()
    {
        if (!$this->validateAdminSession()) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, username, email, last_login FROM admin_users WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get current admin error: " . $e->getMessage());
            return false;
        }
    }
}
