# COMPLETE DEPLOYMENT & INTEGRATION GUIDE

## Quick Start Overview

This guide covers everything you need to deploy your Game Configuration API and integrate it with Unity games.

## 1. Server Setup (cPanel)

### Database Setup
1. In cPanel → MySQL Databases:
   - Create database: `game_config_api`
   - Create user: `gameapi_user` with strong password
   - Add user to database with all privileges

2. Import database schema:
   - Go to phpMyAdmin → Select your database → Import
   - Upload `sql/schema.sql` and `sql/login_attempts_table.sql`

### File Upload
1. Create folder `game-api` in `public_html`
2. Upload all project files to this folder
3. Set permissions: Directories (755), PHP files (644)

### Configuration
1. Create writable directories:
   ```bash
   mkdir cache rate_limits
   chmod 755 cache rate_limits
   ```

2. Copy `.env.example` to `.env` and configure:
   ```bash
   # Database
   DB_HOST=localhost
   DB_NAME=your_cpanel_prefix_game_config_api
   DB_USERNAME=your_cpanel_prefix_gameapi_user
   DB_PASSWORD=your_database_password
   
   # Security
   JWT_SECRET=your-unique-secret-key-32-chars-min
   CORS_ALLOWED_ORIGINS=https://yourgame.com
   
   # API
   ENVIRONMENT=production
   RATE_LIMIT_REQUESTS=100
   RATE_LIMIT_WINDOW=60
   SESSION_LIFETIME=1800
   ```

3. Set permissions: `chmod 600 .env`

### PHP Configuration
1. In cPanel → MultiPHP Manager:
   - Set PHP version to 8.0+ for your `game-api` folder
   - Enable required extensions: PDO, PDO MySQL, JSON, cURL

### SSL Setup
1. In cPanel → SSL/TLS Status:
   - Run AutoSSL for your domain
2. Add to `.htaccess` to force HTTPS:
   ```apache
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

## 2. Admin Setup

1. Access admin panel: `https://yourdomain.com/game-api/admin/`
2. Login with: Username: `admin`, Password: `admin123`
3. **IMPORTANT**: Change password immediately after first login

### Create Your First Game
1. Go to Games → Create New Game
2. Fill in details:
   - Name: Your Game Name
   - Game ID: `my_game_001` (unique identifier)
   - Description: Game description
3. Click Create Game
4. Copy the generated API key

### Add Sample Configuration
1. Go to Configurations
2. Select your game
3. Add configuration:
   - Key: `state.maintenanceMode`
   - Value: `false`
   - Data Type: `boolean`
   - Category: `state`
4. Click Add Configuration

## 3. Unity Integration

### Install Dependencies
1. In Unity Package Manager → Add package from git URL:
   - `https://github.com/JamesNK/Newtonsoft.Json.git`

### Create Configuration Manager
Create `GameConfigManager.cs`:

```csharp
using UnityEngine;
using UnityEngine.Networking;
using System.Collections;
using System.Collections.Generic;
using Newtonsoft.Json;
using System;

public class GameConfigManager : MonoBehaviour
{
    [Header("API Configuration")]
    [SerializeField] private string gameID = "my_game_001";
    [SerializeField] private string apiKey = "your_api_key_here";
    [SerializeField] private string apiBaseUrl = "https://yourdomain.com/game-api/api/v1/config/";
    [SerializeField] private bool fetchOnStart = true;
    [SerializeField] private float refreshInterval = 300f; // 5 minutes
    
    private Dictionary<string, object> configData = new Dictionary<string, object>();
    private bool isConfigLoaded = false;
    
    public static event Action OnConfigLoaded;
    public static event Action<string> OnConfigError;
    
    private void Start()
    {
        if (fetchOnStart)
        {
            FetchConfiguration();
        }
        InvokeRepeating(nameof(FetchConfiguration), refreshInterval, refreshInterval);
    }
    
    public void FetchConfiguration()
    {
        StartCoroutine(FetchConfigurationCoroutine());
    }
    
    private IEnumerator FetchConfigurationCoroutine()
    {
        string url = $"{apiBaseUrl}{gameID}";
        
        using (UnityWebRequest request = UnityWebRequest.Get(url))
        {
            request.SetRequestHeader("X-API-Key", apiKey);
            
            yield return request.SendWebRequest();
            
            if (request.result == UnityWebRequest.Result.Success)
            {
                try
                {
                    string jsonResponse = request.downloadHandler.text;
                    var response = JsonConvert.DeserializeObject<Dictionary<string, object>>(jsonResponse);
                    
                    if (response.ContainsKey("success") && (bool)response["success"])
                    {
                        configData = JsonConvert.DeserializeObject<Dictionary<string, object>>(response["data"].ToString());
                        isConfigLoaded = true;
                        OnConfigLoaded?.Invoke();
                    }
                }
                catch (System.Exception e)
                {
                    OnConfigError?.Invoke($"Failed to parse configuration: {e.Message}");
                }
            }
            else
            {
                OnConfigError?.Invoke($"HTTP Error: {request.error}");
            }
        }
    }
    
    // Configuration Getters
    public bool GetBool(string key, bool defaultValue = false)
    {
        if (configData.TryGetValue(key, out object value))
            return Convert.ToBoolean(value);
        return defaultValue;
    }
    
    public int GetInt(string key, int defaultValue = 0)
    {
        if (configData.TryGetValue(key, out object value))
            return Convert.ToInt32(value);
        return defaultValue;
    }
    
    public float GetFloat(string key, float defaultValue = 0f)
    {
        if (configData.TryGetValue(key, out object value))
            return Convert.ToSingle(value);
        return defaultValue;
    }
    
    public string GetString(string key, string defaultValue = "")
    {
        if (configData.TryGetValue(key, out object value))
            return value.ToString();
        return defaultValue;
    }
    
    public List<string> GetStringList(string key)
    {
        if (configData.TryGetValue(key, out object value))
        {
            try { return JsonConvert.DeserializeObject<List<string>>(value.ToString()); }
            catch { return new List<string>(); }
        }
        return new List<string>();
    }
    
    public Dictionary<string, object> GetDictionary(string key)
    {
        if (configData.TryGetValue(key, out object value))
        {
            try { return JsonConvert.DeserializeObject<Dictionary<string, object>>(value.ToString()); }
            catch { return new Dictionary<string, object>(); }
        }
        return new Dictionary<string, object>();
    }
    
    public bool IsConfigLoaded() => isConfigLoaded;
}
```

### Use Configuration in Your Game
Create `GameManager.cs`:

```csharp
using UnityEngine;

public class GameManager : MonoBehaviour
{
    private GameConfigManager configManager;
    
    private void Start()
    {
        configManager = FindObjectOfType<GameConfigManager>();
        GameConfigManager.OnConfigLoaded += OnConfigLoaded;
        GameConfigManager.OnConfigError += OnConfigError;
        
        if (configManager.IsConfigLoaded())
            OnConfigLoaded();
    }
    
    private void OnConfigLoaded()
    {
        // Apply game settings
        bool maintenanceMode = configManager.GetBool("state.maintenanceMode", false);
        if (maintenanceMode)
        {
            ShowMaintenanceScreen();
            return;
        }
        
        int maxPlayers = configManager.GetInt("config.maxPlayersPerMatch", 10);
        float expMultiplier = configManager.GetFloat("config.experienceMultiplier", 1.0f);
        
        Debug.Log($"Max players: {maxPlayers}, XP Multiplier: {expMultiplier}");
    }
    
    private void OnConfigError(string error)
    {
        Debug.LogError($"Configuration error: {error}");
        // Use default values or show error message
    }
    
    private void ShowMaintenanceScreen()
    {
        Debug.Log("Game is in maintenance mode");
        // Show maintenance UI
    }
}
```

## 4. Testing

### Test API
```bash
curl -H "X-API-Key: your_api_key" https://yourdomain.com/game-api/api/v1/config/my_game_001
```

### Test Unity
1. Add GameConfigManager to a GameObject in your scene
2. Add GameManager to another GameObject
3. Configure your Game ID and API Key in the inspector
4. Run the scene and check console for configuration loading

## 5. Security Checklist

Before going live, ensure:
- [ ] Default admin password changed
- [ ] HTTPS enabled and forced
- [ ] CORS settings restricted to your game domains
- [ ] API keys are secure and not hardcoded in client builds
- [ ] Rate limiting is appropriately configured
- [ ] Database backups are scheduled
- [ ] File permissions are correct

## 6. Common Issues

### Database Connection Errors
- Check `.env` database credentials
- Verify database name includes cPanel prefix
- Ensure database user has all privileges

### Permission Errors
- Set directory permissions to 755
- Set file permissions to 644
- Set `.env` permissions to 600

### API Key Issues
- Verify API key is correctly copied
- Check game status is 'active' in admin panel
- Ensure API key is sent in X-API-Key header

### CORS Issues (WebGL)
- Add your game domain to CORS_ALLOWED_ORIGINS in `.env`
- Test with both header and URL parameter API key methods

## 7. Production Tips

1. **Monitor API Usage**: Check logs for unusual activity
2. **Regular Backups**: Schedule daily database backups
3. **API Key Rotation**: Periodically regenerate API keys
4. **Performance Monitoring**: Monitor response times and error rates
5. **Keep Updated**: Maintain PHP and cPanel updates

That's it! Your Game Configuration API is now deployed and integrated with Unity. You can remotely update game configurations without requiring app updates.