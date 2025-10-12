# Technical Specifications - Game Configuration API

## 1. Database Schema Implementation

### SQL Schema File (sql/schema.sql)

```sql
-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS game_config_api;
USE game_config_api;

-- Games table
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    game_id VARCHAR(50) UNIQUE NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_game_id (game_id),
    INDEX idx_api_key (api_key)
);

-- Configurations table
CREATE TABLE configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    game_id INT NOT NULL,
    config_key VARCHAR(255) NOT NULL,
    config_value JSON NOT NULL,
    data_type ENUM('string', 'number', 'boolean', 'array', 'object') NOT NULL,
    category VARCHAR(50) DEFAULT 'general',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    UNIQUE KEY unique_game_config (game_id, config_key),
    INDEX idx_game_id (game_id),
    INDEX idx_category (category),
    INDEX idx_config_key (config_key)
);

-- Admin users table
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username)
);

-- Insert default admin user (password: admin123)
INSERT INTO admin_users (username, password, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com');
```

## 2. Configuration Files

### Database Configuration (config/database.php)

```php
<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'game_config_api';
    private $username = 'your_db_username';
    private $password = 'your_db_password';
    private $charset = 'utf8mb4';
    
    public $pdo;
    
    public function getConnection() {
        $this->pdo = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->pdo = new PDO($dsn, $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->pdo;
    }
}
?>
```

### Application Configuration (config/config.php)

```php
<?php
// Application settings
define('APP_NAME', 'Game Configuration API');
define('APP_VERSION', '1.0.0');
define('API_VERSION', 'v1');

// Security settings
define('JWT_SECRET', 'your-secret-key-here');
define('SESSION_LIFETIME', 1800); // 30 minutes
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60); // seconds

// API Settings
define('CORS_ALLOWED_ORIGINS', '*');
define('API_RESPONSE_FORMAT', 'json');

// File paths
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CACHE_PATH', ROOT_PATH . '/cache');
define('RATE_LIMIT_PATH', ROOT_PATH . '/rate_limits');
?>
```

## 3. Core Classes

### API Response Handler (includes/ResponseHandler.php)

```php
<?php
class ResponseHandler {
    public static function success($data = null, $meta = []) {
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
    
    public static function error($message, $code = 'INTERNAL_ERROR', $httpStatus = 500) {
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
    
    private static function sendResponse($response, $httpStatus) {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: ' . CORS_ALLOWED_ORIGINS);
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
        
        http_response_code($httpStatus);
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
}
?>
```

### Authentication Class (includes/Auth.php)

```php
<?php
class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function validateApiKey($apiKey) {
        if (empty($apiKey)) {
            return false;
        }
        
        $hashedKey = hash('sha256', $apiKey);
        
        $stmt = $this->pdo->prepare("SELECT id, game_id FROM games WHERE api_key = ? AND status = 'active'");
        $stmt->execute([$hashedKey]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function validateAdminSession() {
        session_start();
        
        if (empty($_SESSION['admin_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            session_destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function loginAdmin($username, $password) {
        $stmt = $this->pdo->prepare("SELECT id, password FROM admin_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            session_start();
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['last_activity'] = time();
            
            // Update last login
            $updateStmt = $this->pdo->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            return true;
        }
        
        return false;
    }
    
    public function generateApiKey() {
        return bin2hex(random_bytes(32));
    }
}
?>
```

### Rate Limiting Class (includes/RateLimiter.php)

```php
<?php
class RateLimiter {
    public static function checkLimit($ip, $limit = RATE_LIMIT_REQUESTS, $window = RATE_LIMIT_WINDOW) {
        if (!file_exists(RATE_LIMIT_PATH)) {
            mkdir(RATE_LIMIT_PATH, 0755, true);
        }
        
        $rateFile = RATE_LIMIT_PATH . '/' . md5($ip) . '.json';
        $now = time();
        
        if (file_exists($rateFile)) {
            $data = json_decode(file_get_contents($rateFile), true);
            
            // Reset if window has passed
            if ($now - $data['reset_time'] > $window) {
                $data = ['count' => 0, 'reset_time' => $now];
            }
            
            // Check limit
            if ($data['count'] >= $limit) {
                return false;
            }
            
            $data['count']++;
        } else {
            $data = ['count' => 1, 'reset_time' => $now];
        }
        
        file_put_contents($rateFile, json_encode($data));
        return true;
    }
}
?>
```

## 4. API Endpoints Implementation

### API Router (api/v1/index.php)

```php
<?php
require_once '../../../config/config.php';
require_once '../../../includes/ResponseHandler.php';
require_once '../../../includes/Auth.php';
require_once '../../../includes/RateLimiter.php';
require_once '../../../config/database.php';

// Initialize database and auth
$database = new Database();
$pdo = $database->getConnection();
$auth = new Auth($pdo);

// Rate limiting
$clientIp = $_SERVER['REMOTE_ADDR'];
if (!RateLimiter::checkLimit($clientIp)) {
    ResponseHandler::error('Rate limit exceeded', 'RATE_LIMIT_EXCEEDED', 429);
}

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: ' . CORS_ALLOWED_ORIGINS);
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    exit;
}

// Get request path
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Route the request
array_shift($pathParts); // Remove 'api'
array_shift($pathParts); // Remove 'v1'

$endpoint = $pathParts[0] ?? '';
$resource = $pathParts[1] ?? '';
$id = $pathParts[2] ?? null;

switch ($endpoint) {
    case 'config':
        require_once 'config.php';
        break;
    case 'admin':
        require_once 'admin/index.php';
        break;
    default:
        ResponseHandler::error('Endpoint not found', 'NOT_FOUND', 404);
}
?>
```

### Configuration Endpoints (api/v1/config.php)

```php
<?php
// Extract game_id from URL
$gameId = $resource ?? null;
$key = $id ?? null;
$category = $_GET['category'] ?? null;

// Validate API key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;
$game = $auth->validateApiKey($apiKey);

if (!$game) {
    ResponseHandler::error('Invalid API key', 'INVALID_API_KEY', 401);
}

// Verify game_id matches the authenticated game
if ($gameId && $gameId !== $game['game_id']) {
    ResponseHandler::error('Access denied', 'ACCESS_DENIED', 403);
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($key) {
            // Get specific configuration key
            $stmt = $pdo->prepare("SELECT config_key, config_value FROM configurations WHERE game_id = ? AND config_key = ?");
            $stmt->execute([$game['id'], $key]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$config) {
                ResponseHandler::error('Configuration not found', 'CONFIG_NOT_FOUND', 404);
            }
            
            ResponseHandler::success([$config['config_key'] => json_decode($config['config_value'])]);
        } elseif ($category) {
            // Get configurations by category
            $stmt = $pdo->prepare("SELECT config_key, config_value FROM configurations WHERE game_id = ? AND category = ?");
            $stmt->execute([$game['id'], $category]);
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            foreach ($configs as $config) {
                $result[$config['config_key']] = json_decode($config['config_value']);
            }
            
            ResponseHandler::success($result, ['category' => $category]);
        } else {
            // Get all configurations for the game
            $stmt = $pdo->prepare("SELECT config_key, config_value FROM configurations WHERE game_id = ?");
            $stmt->execute([$game['id']]);
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            foreach ($configs as $config) {
                $result[$config['config_key']] = json_decode($config['config_value']);
            }
            
            ResponseHandler::success($result, ['game_id' => $game['game_id']]);
        }
    } else {
        ResponseHandler::error('Method not allowed', 'METHOD_NOT_ALLOWED', 405);
    }
} catch (Exception $e) {
    ResponseHandler::error('Internal server error', 'INTERNAL_ERROR', 500);
}
?>
```

## 5. Admin Panel Implementation

### Admin Login (admin/index.php)

```php
<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/Auth.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $pdo = $database->getConnection();
    $auth = new Auth($pdo);
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($auth->loginAdmin($username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Game Configuration API</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }
        .login-container { max-width: 400px; margin: 100px auto; padding: 20px; background: white; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; }
        button:hover { background: #0056b3; }
        .error { color: red; margin-bottom: 15px; }
        h1 { text-align: center; color: #333; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Admin Login</h1>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
```

## 6. .htaccess Configuration

```apache
# Enable URL rewriting
RewriteEngine On

# API routes
RewriteRule ^api/v1/config/([^/]+)/?$ api/v1/config.php?game_id=$1 [L,QSA]
RewriteRule ^api/v1/config/([^/]+)/([^/]+)/?$ api/v1/config.php?game_id=$1&key=$2 [L,QSA]
RewriteRule ^api/v1/admin/?$ api/v1/admin/index.php [L,QSA]
RewriteRule ^api/v1/admin/([^/]+)/?$ api/v1/admin/$1.php [L,QSA]

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>

# PHP settings
php_value display_errors 0
php_value log_errors 1
php_value error_log /path/to/your/error.log
```

These technical specifications provide detailed implementation guidance for each component of the Game Configuration API system. Each file includes proper error handling, security measures, and follows PHP best practices for cPanel hosting environments.