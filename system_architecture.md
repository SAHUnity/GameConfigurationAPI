# Game Configuration API - System Architecture

## Overview
A secure, high-performance PHP-based API system for managing and distributing game configuration data, designed with enterprise-grade security features and optimized for production deployment.

## System Components

### 1. Database Schema Design

#### Tables:
- `games` - Store game information and API keys
- `configurations` - Store key-value configuration pairs
- `admin_users` - Store admin panel user credentials
- `login_attempts` - Track login attempts for brute force protection

#### games Table Structure:
```sql
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    game_id VARCHAR(50) UNIQUE NOT NULL,
    api_key VARCHAR(128) UNIQUE NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### configurations Table Structure:
```sql
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
    UNIQUE KEY unique_game_config (game_id, config_key)
);
```

#### admin_users Table Structure:
```sql
CREATE TABLE admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
);
```

#### login_attempts Table Structure:
```sql
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    success TINYINT(1) NOT NULL,
    username VARCHAR(50),
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_created (identifier, created_at)
);
```

### 2. API Endpoints

#### Configuration Retrieval Endpoints:
- `GET /api/v1/config/{game_id}` - Get all configurations for a game
- `GET /api/v1/config/{game_id}/{key}` - Get specific configuration key
- `GET /api/v1/config/{game_id}?category={category}` - Get configurations by category

#### Game Management Endpoints (Admin):
- `POST /api/v1/admin/games` - Create new game
- `PUT /api/v1/admin/games/{id}` - Update game details
- `DELETE /api/v1/admin/games/{id}` - Delete game
- `GET /api/v1/admin/games` - List all games

#### Configuration Management Endpoints (Admin):
- `POST /api/v1/admin/config` - Add/update configuration
- `PUT /api/v1/admin/config/{id}` - Update configuration
- `DELETE /api/v1/admin/config/{id}` - Delete configuration
- `GET /api/v1/admin/config` - List all configurations

### 3. Authentication System

#### API Authentication:
- Strong API key validation via HTTP header: `X-API-Key: {api_key}`
- OR as URL parameter: `?api_key={api_key}`
- API keys stored as SHA384 + RIPEMD160 hashes in database
- Rate limiting per IP address

#### Admin Panel Authentication:
- Session-based authentication with username/password
- Passwords stored using password_hash() with bcrypt
- Session timeout after configurable period (default 30 minutes)
- Brute force protection with configurable limits

### 4. File Structure

```
/
├── api/
│   ├── v1/
│   │   ├── index.php           # API router
│   │   ├── config.php          # Configuration endpoints
│   │   └── admin/
│   │       ├── index.php       # Admin API router
│   │       ├── games.php       # Admin game management
│   │       └── config.php      # Admin config management
├── admin/
│   ├── index.php               # Admin panel login
│   ├── dashboard.php           # Main admin dashboard
│   ├── games.php               # Game management interface
│   ├── configurations.php      # Configuration management
│   └── logout.php              # Logout handler
├── config/
│   ├── database.php            # Database singleton class
│   └── config.php              # Application configuration
├── includes/
│   ├── Auth.php                # Authentication class
│   ├── AuthMiddleware.php      # Authentication middleware
│   ├── SecurityMiddleware.php  # Security middleware
│   ├── RateLimiter.php         # Rate limiting class
│   ├── ResponseHandler.php     # API response handler
│   └── UtilityFunctions.php    # Shared utility functions
├── sql/
│   ├── schema.sql              # Database schema
│   └── login_attempts_table.sql # Login attempts table
├── logs/                       # Application logs directory
├── cache/                      # Cache directory
├── rate_limits/                # Rate limiting data directory
├── .env.example                # Environment configuration template
└── .htaccess                   # URL rewriting rules
```

### 5. API Response Format

#### Success Response:
```json
{
    "success": true,
    "data": {
        "config.maintenanceMode": false,
        "config.maxPlayersPerMatch": 10
    },
    "meta": {
        "version": "1.0.0",
        "timestamp": "2025-01-15T10:30:00Z"
    }
}
```

#### Error Response:
```json
{
    "success": false,
    "error": {
        "code": "INVALID_API_KEY",
        "message": "The provided API key is invalid or expired"
    },
    "meta": {
        "timestamp": "2025-01-15T10:30:00Z"
    }
}
```

### 6. Security Measures

#### Input Validation:
- Comprehensive input validation using SecurityMiddleware
- Type checking and length validation for all inputs
- Sanitization of all user inputs
- JSON structure validation for configuration values

#### SQL Injection Prevention:
- All database queries use prepared statements
- Parameter binding for all user input
- Singleton database connection pattern

#### Brute Force Protection:
- Login attempt tracking with database table
- Configurable limits and lockout times
- IP-based and username-based tracking

#### Rate Limiting:
- File-based rate limiting with static caching
- Configurable limits per IP address
- Automatic cleanup of old rate limit files

#### Authentication Security:
- Strong API key hashing (SHA384 + RIPEMD160)
- Secure password hashing with bcrypt
- Session timeout management
- Secure logout with session destruction

### 7. Admin Panel Features

#### Dashboard:
- Overview of all games and their status
- Statistics on configuration count
- Recent games and configurations

#### Game Management:
- Add/edit/delete games with validation
- Generate/regenerate API keys
- View game configuration count

#### Configuration Management:
- Add/edit/delete configuration key-value pairs
- Category-based organization
- Support for multiple data types (string, number, boolean, array, object)

### 8. Performance Optimizations

#### Database Optimizations:
- Singleton connection pattern with persistent connections
- Prepared statements for all queries
- Static statement caching for frequently used queries
- Proper indexing on all tables

#### Memory Optimizations:
- Static caching for frequently used data
- Optimized object creation patterns
- Efficient resource management

#### Processing Optimizations:
- Streamlined JSON error handling
- Optimized rate limiting with file path caching
- Efficient input validation patterns

### 9. Deployment Considerations

#### Requirements:
- PHP 7.4+ (preferably 8.0+)
- MySQL/MariaDB database
- mod_rewrite enabled for clean URLs
- File permissions for cache, logs, and rate_limits directories

#### Security Configuration:
- Environment-based configuration
- Secure file permissions
- HTTPS/SSL certificate
- Security headers

## Architecture Patterns

### Design Patterns Used:
- **Singleton Pattern**: Database connections
- **Middleware Pattern**: Authentication and security
- **Factory Pattern**: Object creation where needed
- **Dependency Injection**: Clean dependency management

### Performance Patterns:
- **Static Caching**: For frequently used data
- **Connection Pooling**: Database connections
- **Resource Management**: Efficient cleanup

## Security Architecture

### Defense in Depth:
1. **Input Validation Layer**: Comprehensive validation and sanitization
2. **Authentication Layer**: Strong authentication mechanisms
3. **Authorization Layer**: Proper access control
4. **Database Security Layer**: Prepared statements and connection security
5. **Transport Layer**: HTTPS and secure headers

### Security Features:
- Brute force protection
- Rate limiting
- Input validation and sanitization
- SQL injection prevention
- XSS protection
- Secure session management
- Strong password hashing
- API key security

This architecture provides a robust, secure, and high-performance foundation for a game configuration system with enterprise-grade security features.