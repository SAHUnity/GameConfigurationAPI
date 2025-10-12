# Game Configuration API - System Architecture

## Overview
A PHP-based API system for managing and distributing game configuration data to Unity clients, designed to run on cheap cPanel hosting with MySQL/MariaDB database.

## System Components

### 1. Database Schema Design

#### Tables:
- `games` - Store game information and API keys
- `configurations` - Store key-value configuration pairs
- `admin_users` - Store admin panel user credentials

#### games Table Structure:
```sql
CREATE TABLE games (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    game_id VARCHAR(50) UNIQUE NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL,
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

### 2. API Endpoints

#### Configuration Retrieval Endpoints:
- `GET /api/v1/config/{game_id}` - Get all configurations for a game
- `GET /api/v1/config/{game_id}/{key}` - Get specific configuration key
- `GET /api/v1/config/{game_id}/category/{category}` - Get configurations by category

#### Game Management Endpoints (Admin):
- `POST /api/v1/admin/games` - Create new game
- `PUT /api/v1/admin/games/{id}` - Update game details
- `DELETE /api/v1/admin/games/{id}` - Delete game
- `GET /api/v1/admin/games` - List all games

#### Configuration Management Endpoints (Admin):
- `POST /api/v1/admin/config` - Add/update configuration
- `DELETE /api/v1/admin/config/{id}` - Delete configuration
- `GET /api/v1/admin/config/{game_id}` - Get all configurations for a game

### 3. Authentication System

#### API Authentication:
- Simple API key validation via HTTP header: `X-API-Key: {api_key}`
- OR as URL parameter: `?api_key={api_key}`
- API keys stored as SHA-256 hashes in database

#### Admin Panel Authentication:
- Session-based authentication with username/password
- Passwords stored using password_hash() with bcrypt
- Session timeout after 30 minutes of inactivity

### 4. File Structure

```
/
├── api/
│   ├── v1/
│   │   ├── index.php           # API router
│   │   ├── config.php          # Configuration endpoints
│   │   ├── games.php           # Game management endpoints
│   │   └── admin/
│   │       ├── games.php       # Admin game management
│   │       └── config.php      # Admin config management
├── admin/
│   ├── index.php               # Admin panel login
│   ├── dashboard.php           # Main admin dashboard
│   ├── games.php               # Game management interface
│   ├── configurations.php      # Configuration management
│   └── logout.php              # Logout handler
├── config/
│   ├── database.php            # Database configuration
│   └── config.php              # Application configuration
├── includes/
│   ├── functions.php           # Utility functions
│   ├── auth.php                # Authentication helpers
│   └── db.php                  # Database connection
├── sql/
│   └── schema.sql              # Database schema
├── docs/
│   ├── api_documentation.md    # API docs for Unity developers
│   └── deployment_guide.md     # cPanel deployment guide
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
- Validate all input data types and formats
- Sanitize strings to prevent XSS
- Validate JSON structure for configuration values

#### SQL Injection Prevention:
- Use prepared statements for all database queries
- Parameter binding for all user input

#### Rate Limiting:
- Simple rate limiting based on IP address
- Maximum 100 requests per minute per IP

#### CORS Configuration:
- Configure appropriate CORS headers for Unity WebGL builds
- Allow specific domains or configure per-game access

### 7. Admin Panel Features

#### Dashboard:
- Overview of all games and their status
- Quick stats on configuration count
- Recent activity log

#### Game Management:
- Add/edit/delete games
- Generate/regenerate API keys
- View game usage statistics

#### Configuration Management:
- Add/edit/delete configuration key-value pairs
- Bulk import/export configurations
- Configuration history/backup
- Category-based organization

### 8. Deployment Considerations

#### cPanel Requirements:
- PHP 7.4+ (preferably 8.0+)
- MySQL/MariaDB database
- mod_rewrite enabled for clean URLs
- File upload permissions for admin panel

#### Performance Optimizations:
- Database indexing on frequently queried fields
- Simple caching mechanism for frequently accessed configurations
- Gzip compression for API responses

### 9. Unity Integration Example

#### C# Script Example:
```csharp
using UnityEngine;
using UnityEngine.Networking;
using System.Collections;

public class GameConfigManager : MonoBehaviour
{
    private string gameID = "your_game_id";
    private string apiKey = "your_api_key";
    private string apiURL = "https://your-domain.com/api/v1/config/";
    
    private void Start()
    {
        StartCoroutine(FetchConfiguration());
    }
    
    IEnumerator FetchConfiguration()
    {
        string url = $"{apiURL}{gameID}";
        UnityWebRequest request = UnityWebRequest.Get(url);
        request.SetRequestHeader("X-API-Key", apiKey);
        
        yield return request.SendWebRequest();
        
        if (request.result == UnityWebRequest.Result.Success)
        {
            string jsonData = request.downloadHandler.text;
            // Process configuration data
            ProcessConfigurationData(jsonData);
        }
        else
        {
            Debug.LogError("Failed to fetch configuration: " + request.error);
        }
    }
    
    private void ProcessConfigurationData(string jsonData)
    {
        // Parse JSON and apply configurations
        // Implementation depends on your game's needs
    }
}
```

## Implementation Phases

1. **Phase 1**: Database setup and core API endpoints
2. **Phase 2**: Authentication system and security measures
3. **Phase 3**: Admin panel development
4. **Phase 4**: Documentation and deployment guides
5. **Phase 5**: Testing and optimization

This architecture provides a solid foundation for a scalable, secure game configuration system that works well with cPanel hosting constraints.