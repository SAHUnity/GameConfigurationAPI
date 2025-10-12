# Game Configuration API for Unity Games

A PHP-based API system for managing and distributing game configuration data to Unity clients, designed to run on cheap cPanel hosting with MySQL/MariaDB database.

## 🎯 Overview

This system provides a centralized configuration management solution for Unity games, allowing developers to:
- Dynamically control game settings without app updates
- Manage multiple games from a single admin panel
- Distribute key-value configuration pairs flexibly
- Support various data types (booleans, numbers, strings, arrays, objects)
- Implement real-time feature flags and content control

## 🏗️ System Architecture

```
Unity Client ←→ API Endpoints ←→ Authentication ←→ MySQL Database
                                    ↑
                              Admin Panel
```

### Key Components

- **RESTful API**: JSON-based endpoints for configuration retrieval
- **Admin Panel**: Web interface for managing games and configurations
- **Authentication**: Simple API key system for game clients
- **Database**: MySQL/MariaDB for data persistence
- **Security**: Rate limiting, input validation, and CORS protection

## 📋 Features

### API Features
- ✅ RESTful JSON API
- ✅ Simple API key authentication
- ✅ Support for multiple data types
- ✅ Category-based configuration organization
- ✅ Rate limiting and security measures
- ✅ CORS support for WebGL builds

### Admin Panel Features
- ✅ Game management (create, edit, delete)
- ✅ Configuration management (add, update, delete)
- ✅ API key generation and management
- ✅ Category-based organization
- ✅ Basic authentication system

### Configuration Types Supported

#### Game States & Flags
```json
{
  "state.maintenanceMode": false,
  "state.pvpEnabled": true,
  "state.chatEnabled": false
}
```

#### Game Configuration
```json
{
  "config.maxPlayersPerMatch": 10,
  "config.experienceMultiplier": 2.0,
  "config.energyRegenMinutes": 5
}
```

#### Content Control Lists
```json
{
  "list.enabledLevels": ["level_1", "level_2", "boss_winter"],
  "list.disabledItems": ["broken_sword", "op_shield"]
}
```

#### Complex Data Objects
```json
{
  "data.economyRates": {
    "gold_to_gems": 100,
    "gems_to_premium": 10
  },
  "data.regionSettings": {
    "allowedCountries": ["US", "CA", "UK"]
  }
}
```

## 🚀 Quick Start

### Prerequisites
- cPanel hosting with PHP 7.4+ (preferably 8.0+)
- MySQL/MariaDB database
- Ability to upload files and set permissions

### Installation Steps

1. **Database Setup**
   ```sql
   -- Import sql/schema.sql into your MySQL database
   ```

2. **File Upload**
   - Upload all files to your `public_html/game-api` directory
   - Set appropriate file permissions (644 for files, 755 for directories)

3. **Configuration**
   - Edit `config/database.php` with your database credentials
   - Update `config/config.php` with your security settings

4. **Access Admin Panel**
   - Navigate to `https://yourdomain.com/game-api/admin/`
   - Login with username: `admin`, password: `admin123`
   - **Important**: Change the default password immediately!

5. **Create Your First Game**
   - Add a new game in the admin panel
   - Copy the generated API key
   - Add some sample configurations

## 📚 Documentation

### Core Documentation
- [System Architecture](system_architecture.md) - Detailed system design and architecture
- [Implementation Plan](implementation_plan.md) - Step-by-step implementation guide with flowcharts
- [Technical Specifications](technical_specifications.md) - Detailed code specifications and examples

### Integration Guides
- [Unity Integration Guide](unity_integration_guide.md) - Complete Unity C# integration examples
- [cPanel Deployment Guide](cpanel_deployment_guide.md) - Step-by-step deployment instructions

## 🔧 API Endpoints

### Configuration Retrieval

#### Get All Configurations
```http
GET /api/v1/config/{game_id}
Headers: X-API-Key: {your_api_key}
```

#### Get Specific Configuration
```http
GET /api/v1/config/{game_id}/{key}
Headers: X-API-Key: {your_api_key}
```

#### Get Configurations by Category
```http
GET /api/v1/config/{game_id}/category/{category}
Headers: X-API-Key: {your_api_key}
```

### Response Format
```json
{
  "success": true,
  "data": {
    "config.maxPlayersPerMatch": 10,
    "state.maintenanceMode": false
  },
  "meta": {
    "version": "1.0.0",
    "timestamp": "2025-01-15T10:30:00Z",
    "game_id": "your_game_id"
  }
}
```

## 🎮 Unity Integration

### Basic Usage
```csharp
public class GameManager : MonoBehaviour
{
    private GameConfigManager configManager;
    
    private void Start()
    {
        configManager = FindObjectOfType<GameConfigManager>();
        GameConfigManager.OnConfigLoaded += OnConfigLoaded;
    }
    
    private void OnConfigLoaded()
    {
        bool maintenanceMode = configManager.GetBool("state.maintenanceMode");
        int maxPlayers = configManager.GetInt("config.maxPlayersPerMatch");
        float expMultiplier = configManager.GetFloat("config.experienceMultiplier");
        
        // Apply configuration to your game systems
    }
}
```

For complete integration examples, see the [Unity Integration Guide](unity_integration_guide.md).

## 🔒 Security Features

- **API Key Authentication**: Secure key-based authentication for game clients
- **Rate Limiting**: Prevent abuse with configurable rate limits
- **Input Validation**: Comprehensive input sanitization and validation
- **SQL Injection Protection**: Prepared statements for all database queries
- **CORS Configuration**: Configurable CORS headers for cross-origin requests
- **Session Management**: Secure admin panel with session timeout

## 📊 Database Schema

### Tables Overview

#### `games`
- Stores game information and API keys
- Fields: id, name, game_id, api_key, description, status

#### `configurations`
- Stores key-value configuration pairs
- Fields: id, game_id, config_key, config_value, data_type, category

#### `admin_users`
- Stores admin panel user credentials
- Fields: id, username, password, email, last_login

## 🛠️ Development Status

### Completed
- ✅ Database schema design
- ✅ System architecture planning
- ✅ Technical specifications
- ✅ Unity integration guide
- ✅ cPanel deployment guide
- ✅ API documentation

### Pending Implementation
- ⏳ Database connection and configuration files
- ⏳ Core API endpoints implementation
- ⏳ Authentication system
- ⏳ Admin panel development
- ⏳ Security measures implementation
- ⏳ Testing and validation

## 🤝 Contributing

This project is designed to be a comprehensive solution for Unity game configuration management. The architecture and documentation provide a solid foundation for implementation.

## 📄 License

This project is provided as-is for educational and commercial use. Please ensure you comply with your hosting provider's terms of service.

## 🆘 Support

For implementation support:
1. Review the documentation files in this repository
2. Check the troubleshooting section in the deployment guide
3. Ensure your cPanel hosting meets the requirements

## 🔄 Version History

- **v1.0.0** - Initial architecture and documentation
  - Complete system design
  - Database schema
  - API specifications
  - Unity integration examples
  - cPanel deployment guide

---

**Note**: This repository contains the complete architecture, specifications, and documentation for the Game Configuration API. The actual implementation files need to be created based on the technical specifications provided.