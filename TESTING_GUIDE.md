# Testing Guide - Game Configuration API

This guide provides step-by-step instructions for testing the complete Game Configuration API system.

## 🚀 Quick Testing Checklist

- [ ] Database setup with schema and test data
- [ ] API endpoints responding correctly
- [ ] Admin panel functional
- [ ] Unity integration working
- [ ] Security measures active

## 📋 Prerequisites

1. **cPanel Hosting** with PHP 7.4+ and MySQL/MariaDB
2. **Database Access** (phpMyAdmin or similar)
3. **Web Browser** for testing admin panel
4. **API Testing Tool** (Postman, curl, or similar)
5. **Unity Editor** (for Unity integration testing)

## 🗄️ Database Setup

### 1. Create Database
1. Log in to cPanel
2. Navigate to **MySQL Databases**
3. Create a new database: `game_config_api`
4. Create a database user with strong password
5. Add the user to the database with all privileges

### 2. Import Schema
1. Navigate to **phpMyAdmin**
2. Select your database
3. Click **Import**
4. Upload `sql/schema.sql`
5. Execute the import

### 3. Import Test Data
1. In phpMyAdmin, select your database
2. Click **Import**
3. Upload `sql/test_data.sql`
4. Execute the import

### 4. Verify Database
You should see:
- 3 games in the `games` table
- 60+ configurations in the `configurations` table
- 1 admin user in the `admin_users` table

## 🔧 Configuration Setup

### 1. Update Database Configuration
Edit `config/database.php`:

```php
private $host = 'localhost';
private $db_name = 'your_cpanel_prefix_game_config_api'; // Include cPanel prefix
private $username = 'your_cpanel_prefix_gameapi_user'; // Include cPanel prefix
private $password = 'your_database_password';
```

### 2. Update Application Configuration
Edit `config/config.php`:

```php
define('JWT_SECRET', 'your-unique-secret-key-here');
define('CORS_ALLOWED_ORIGINS', '*'); // Restrict in production
```

### 3. Set File Permissions
- Create `cache/` and `rate_limits/` directories
- Set permissions to 755 for directories
- Set permissions to 644 for PHP files

## 🌐 API Testing

### Test API Endpoints with curl

#### 1. Test Invalid API Key
```bash
curl -H "X-API-Key: invalid_key" https://yourdomain.com/game-api/api/v1/config/space_adventure_001
```
Expected: Error response with "Invalid API key"

#### 2. Test Valid API Key
```bash
curl -H "X-API-Key: test_api_key_1" https://yourdomain.com/game-api/api/v1/config/space_adventure_001
```
Expected: Success response with configuration data

#### 3. Test Specific Configuration Key
```bash
curl -H "X-API-Key: test_api_key_1" https://yourdomain.com/game-api/api/v1/config/space_adventure_001/config.maxPlayersPerMatch
```
Expected: Success response with specific configuration value

#### 4. Test Category Filter
```bash
curl -H "X-API-Key: test_api_key_1" "https://yourdomain.com/game-api/api/v1/config/space_adventure_001?category=state"
```
Expected: Success response with state configurations only

### Test API Keys from Test Data
- **Space Adventure**: `test_api_key_1` (Game ID: `space_adventure_001`)
- **Fantasy Quest**: `test_api_key_2` (Game ID: `fantasy_quest_001`)
- **Racing Pro**: `test_api_key_3` (Game ID: `racing_pro_001`)

## 🖥️ Admin Panel Testing

### 1. Access Admin Panel
Navigate to: `https://yourdomain.com/game-api/admin/`

### 2. Login
- Username: `admin`
- Password: `admin123`

### 3. Test Dashboard
- Verify statistics display correctly
- Check recent games and configurations

### 4. Test Game Management
1. Go to **Games** page
2. View existing games
3. Create a new test game:
   - Name: `Test Game`
   - Game ID: `test_game_001`
   - Description: `Test game for API testing`
4. Copy the generated API key
5. Test the new game with the API

### 5. Test Configuration Management
1. Go to **Configurations** page
2. Filter by game and category
3. Create a new configuration:
   - Game: Select your test game
   - Key: `test.config`
   - Value: `test value`
   - Type: `string`
   - Category: `test`
4. Test the new configuration via API

## 🎮 Unity Integration Testing

### 1. Setup Unity Project
1. Create a new Unity project
2. Install Newtonsoft.Json package via Package Manager
3. Create a GameObject and add the `GameConfigManager` script

### 2. Configure GameConfigManager
- **Game ID**: `space_adventure_001`
- **API Key**: `test_api_key_1`
- **API Base URL**: `https://yourdomain.com/game-api/api/v1/config/`

### 3. Test Configuration Loading
```csharp
public class TestGameConfig : MonoBehaviour
{
    private GameConfigManager configManager;
    
    void Start()
    {
        configManager = FindObjectOfType<GameConfigManager>();
        GameConfigManager.OnConfigLoaded += OnConfigLoaded;
        GameConfigManager.OnConfigError += OnConfigError;
    }
    
    void OnConfigLoaded()
    {
        Debug.Log("Configuration loaded!");
        
        // Test different data types
        bool maintenanceMode = configManager.GetBool("state.maintenanceMode");
        int maxPlayers = configManager.GetInt("config.maxPlayersPerMatch");
        float expMultiplier = configManager.GetFloat("config.experienceMultiplier");
        string apiVersion = configManager.GetString("meta.apiVersion");
        var enabledLevels = configManager.GetStringList("list.enabledLevels");
        var economyRates = configManager.GetDictionary("data.economyRates");
        
        Debug.Log($"Maintenance Mode: {maintenanceMode}");
        Debug.Log($"Max Players: {maxPlayers}");
        Debug.Log($"XP Multiplier: {expMultiplier}");
        Debug.Log($"API Version: {apiVersion}");
        Debug.Log($"Enabled Levels: {string.Join(", ", enabledLevels)}");
    }
    
    void OnConfigError(string error)
    {
        Debug.LogError($"Configuration error: {error}");
    }
}
```

### 4. Test Error Handling
- Test with invalid API key
- Test with invalid game ID
- Test network connectivity issues

## 🔒 Security Testing

### 1. Rate Limiting
Make multiple rapid requests to test rate limiting:
```bash
for i in {1..101}; do
    curl -H "X-API-Key: test_api_key_1" https://yourdomain.com/game-api/api/v1/config/space_adventure_001
done
```
Expected: Rate limit error after 100 requests

### 2. Input Validation
Test SQL injection attempts:
```bash
curl -H "X-API-Key: test_api_key_1" "https://yourdomain.com/game-api/api/v1/config/space_adventure_001/' OR '1'='1"
```
Expected: Error response, no data returned

### 3. CORS Testing
Test from different origins to verify CORS headers are properly set.

## 📊 Performance Testing

### 1. Load Testing
Use a tool like Apache Bench (ab) to test performance:
```bash
ab -n 1000 -c 10 -H "X-API-Key: test_api_key_1" https://yourdomain.com/game-api/api/v1/config/space_adventure_001
```

### 2. Response Time Testing
Measure API response times with different data sizes.

## 🐛 Common Issues and Solutions

### Database Connection Issues
- **Problem**: "Database connection failed"
- **Solution**: Verify database credentials in `config/database.php`
- **Check**: cPanel database name prefixes

### Permission Issues
- **Problem**: "Permission denied" errors
- **Solution**: Check file and directory permissions
- **Fix**: `chmod 755` for directories, `chmod 644` for files

### API Key Issues
- **Problem**: "Invalid API key" errors
- **Solution**: Verify API key is correct and game is active
- **Check**: Game status in admin panel

### CORS Issues
- **Problem**: CORS errors in Unity WebGL
- **Solution**: Update CORS_ALLOWED_ORIGINS in config
- **Fix**: Add your game domain to allowed origins

## ✅ Success Criteria

Your API is working correctly if:

- [ ] Database tables are created and populated
- [ ] API endpoints return correct responses
- [ ] Admin panel loads and functions properly
- [ ] Unity client can fetch configuration data
- [ ] Rate limiting prevents abuse
- [ ] Input validation blocks malicious requests
- [ ] CORS headers are set correctly
- [ ] Error handling works as expected

## 📝 Test Results Template

```
=== Game Configuration API Test Results ===
Date: [Date]
Tester: [Your Name]

Database Setup:
✅ Schema imported successfully
✅ Test data imported successfully
✅ Permissions set correctly

API Testing:
✅ Invalid API key rejected
✅ Valid API key accepted
✅ Configuration data returned
✅ Specific keys work
✅ Category filtering works

Admin Panel:
✅ Login successful
✅ Dashboard displays correctly
✅ Game management works
✅ Configuration management works

Unity Integration:
✅ Configuration loads successfully
✅ Different data types handled correctly
✅ Error handling works

Security:
✅ Rate limiting active
✅ Input validation working
✅ CORS headers set

Performance:
✅ Response times acceptable
✅ Load testing passed

Overall Status: ✅ READY FOR PRODUCTION
```

## 🚀 Next Steps

After successful testing:

1. **Change Default Password**: Update admin password
2. **Update API Keys**: Generate new API keys for production
3. **Configure CORS**: Restrict to your game domains
4. **Set Up Monitoring**: Monitor API usage and errors
5. **Document API**: Share API documentation with your team

## 📞 Support

If you encounter issues during testing:

1. Check PHP error logs in cPanel
2. Verify database connections
3. Review file permissions
4. Test with different browsers/tools
5. Consult the troubleshooting section in the deployment guide

This testing guide ensures your Game Configuration API is fully functional and ready for production use with your Unity games.