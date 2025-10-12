# Unity Integration Guide - Game Configuration API

## Overview

This guide provides detailed instructions for integrating Unity games with the Game Configuration API. The integration allows your Unity games to dynamically fetch configuration data from your PHP-based API server.

## Prerequisites

- Unity 2019.4 LTS or later
- Internet connection in your game
- Valid Game ID and API Key from your Game Configuration API

## 1. Basic Unity C# Integration

### Configuration Manager Script

Create a new C# script called `GameConfigManager.cs`:

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
    [SerializeField] private string gameID = "your_game_id";
    [SerializeField] private string apiKey = "your_api_key";
    [SerializeField] private string apiBaseUrl = "https://your-domain.com/api/v1/config/";
    [SerializeField] private bool fetchOnStart = true;
    [SerializeField] private float refreshInterval = 300f; // 5 minutes
    
    [Header("Debug")]
    [SerializeField] private bool enableDebugLogs = true;
    
    // Configuration data storage
    private Dictionary<string, object> configData = new Dictionary<string, object>();
    private bool isConfigLoaded = false;
    private string lastError = "";
    
    // Events
    public static event Action OnConfigLoaded;
    public static event Action<string> OnConfigError;
    
    private void Start()
    {
        if (fetchOnStart)
        {
            FetchConfiguration();
        }
        
        // Start periodic refresh
        InvokeRepeating(nameof(FetchConfiguration), refreshInterval, refreshInterval);
    }
    
    /// <summary>
    /// Fetches all configuration data from the API
    /// </summary>
    public void FetchConfiguration()
    {
        StartCoroutine(FetchConfigurationCoroutine());
    }
    
    private IEnumerator FetchConfigurationCoroutine()
    {
        string url = $"{apiBaseUrl}{gameID}";
        
        if (enableDebugLogs)
            Debug.Log($"Fetching configuration from: {url}");
        
        using (UnityWebRequest request = UnityWebRequest.Get(url))
        {
            request.SetRequestHeader("X-API-Key", apiKey);
            request.SetRequestHeader("Content-Type", "application/json");
            
            yield return request.SendWebRequest();
            
            if (request.result == UnityWebRequest.Result.Success)
            {
                try
                {
                    string jsonResponse = request.downloadHandler.text;
                    ProcessConfigurationResponse(jsonResponse);
                }
                catch (System.Exception e)
                {
                    HandleError($"Failed to parse configuration: {e.Message}");
                }
            }
            else
            {
                HandleError($"HTTP Error: {request.error} (Code: {request.responseCode})");
            }
        }
    }
    
    /// <summary>
    /// Fetches a specific configuration key
    /// </summary>
    /// <param name="key">Configuration key to fetch</param>
    /// <param name="callback">Callback with the configuration value</param>
    public void FetchConfigurationKey(string key, System.Action<object> callback)
    {
        StartCoroutine(FetchConfigurationKeyCoroutine(key, callback));
    }
    
    private IEnumerator FetchConfigurationKeyCoroutine(string key, System.Action<object> callback)
    {
        string url = $"{apiBaseUrl}{gameID}/{key}";
        
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
                        var data = JsonConvert.DeserializeObject<Dictionary<string, object>>(response["data"].ToString());
                        if (data.ContainsKey(key))
                        {
                            callback?.Invoke(data[key]);
                        }
                    }
                }
                catch (System.Exception e)
                {
                    if (enableDebugLogs)
                        Debug.LogError($"Failed to parse configuration key: {e.Message}");
                }
            }
            else
            {
                if (enableDebugLogs)
                    Debug.LogError($"Failed to fetch configuration key: {request.error}");
            }
        }
    }
    
    /// <summary>
    /// Processes the JSON response from the API
    /// </summary>
    /// <param name="jsonResponse">JSON response string</param>
    private void ProcessConfigurationResponse(string jsonResponse)
    {
        try
        {
            var response = JsonConvert.DeserializeObject<Dictionary<string, object>>(jsonResponse);
            
            if (response.ContainsKey("success") && (bool)response["success"])
            {
                var data = JsonConvert.DeserializeObject<Dictionary<string, object>>(response["data"].ToString());
                configData = data;
                isConfigLoaded = true;
                lastError = "";
                
                if (enableDebugLogs)
                    Debug.Log($"Configuration loaded successfully with {configData.Count} entries");
                
                OnConfigLoaded?.Invoke();
            }
            else
            {
                string errorMessage = "Unknown error";
                if (response.ContainsKey("error"))
                {
                    var error = JsonConvert.DeserializeObject<Dictionary<string, object>>(response["error"].ToString());
                    errorMessage = error.ContainsKey("message") ? error["message"].ToString() : errorMessage;
                }
                
                HandleError($"API Error: {errorMessage}");
            }
        }
        catch (System.Exception e)
        {
            HandleError($"Failed to process configuration: {e.Message}");
        }
    }
    
    /// <summary>
    /// Handles errors during configuration fetching
    /// </summary>
    /// <param name="error">Error message</param>
    private void HandleError(string error)
    {
        lastError = error;
        isConfigLoaded = false;
        
        if (enableDebugLogs)
            Debug.LogError($"Configuration Error: {error}");
        
        OnConfigError?.Invoke(error);
    }
    
    // Configuration Getters
    
    /// <summary>
    /// Gets a boolean configuration value
    /// </summary>
    /// <param name="key">Configuration key</param>
    /// <param name="defaultValue">Default value if key not found</param>
    /// <returns>Configuration value or default</returns>
    public bool GetBool(string key, bool defaultValue = false)
    {
        if (configData.TryGetValue(key, out object value))
        {
            return Convert.ToBoolean(value);
        }
        return defaultValue;
    }
    
    /// <summary>
    /// Gets an integer configuration value
    /// </summary>
    /// <param name="key">Configuration key</param>
    /// <param name="defaultValue">Default value if key not found</param>
    /// <returns>Configuration value or default</returns>
    public int GetInt(string key, int defaultValue = 0)
    {
        if (configData.TryGetValue(key, out object value))
        {
            return Convert.ToInt32(value);
        }
        return defaultValue;
    }
    
    /// <summary>
    /// Gets a float configuration value
    /// </summary>
    /// <param name="key">Configuration key</param>
    /// <param name="defaultValue">Default value if key not found</param>
    /// <returns>Configuration value or default</returns>
    public float GetFloat(string key, float defaultValue = 0f)
    {
        if (configData.TryGetValue(key, out object value))
        {
            return Convert.ToSingle(value);
        }
        return defaultValue;
    }
    
    /// <summary>
    /// Gets a string configuration value
    /// </summary>
    /// <param name="key">Configuration key</param>
    /// <param name="defaultValue">Default value if key not found</param>
    /// <returns>Configuration value or default</returns>
    public string GetString(string key, string defaultValue = "")
    {
        if (configData.TryGetValue(key, out object value))
        {
            return value.ToString();
        }
        return defaultValue;
    }
    
    /// <summary>
    /// Gets a list configuration value
    /// </summary>
    /// <param name="key">Configuration key</param>
    /// <returns>List of strings or empty list</returns>
    public List<string> GetStringList(string key)
    {
        if (configData.TryGetValue(key, out object value))
        {
            try
            {
                return JsonConvert.DeserializeObject<List<string>>(value.ToString());
            }
            catch
            {
                return new List<string>();
            }
        }
        return new List<string>();
    }
    
    /// <summary>
    /// Gets a dictionary configuration value
    /// </summary>
    /// <param name="key">Configuration key</param>
    /// <returns>Dictionary or empty dictionary</returns>
    public Dictionary<string, object> GetDictionary(string key)
    {
        if (configData.TryGetValue(key, out object value))
        {
            try
            {
                return JsonConvert.DeserializeObject<Dictionary<string, object>>(value.ToString());
            }
            catch
            {
                return new Dictionary<string, object>();
            }
        }
        return new Dictionary<string, object>();
    }
    
    // Utility methods
    
    /// <summary>
    /// Checks if configuration is loaded
    /// </summary>
    /// <returns>True if configuration is loaded</returns>
    public bool IsConfigLoaded()
    {
        return isConfigLoaded;
    }
    
    /// <summary>
    /// Gets the last error message
    /// </summary>
    /// <returns>Last error message</returns>
    public string GetLastError()
    {
        return lastError;
    }
    
    /// <summary>
    /// Gets all configuration keys
    /// </summary>
    /// <returns>List of configuration keys</returns>
    public List<string> GetAllKeys()
    {
        return new List<string>(configData.Keys);
    }
    
    /// <summary>
    /// Checks if a configuration key exists
    /// </summary>
    /// <param name="key">Configuration key</param>
    /// <returns>True if key exists</returns>
    public bool HasKey(string key)
    {
        return configData.ContainsKey(key);
    }
}
```

## 2. Usage Examples

### Basic Usage in a Unity Script

```csharp
using UnityEngine;

public class GameManager : MonoBehaviour
{
    private GameConfigManager configManager;
    
    private void Start()
    {
        configManager = FindObjectOfType<GameConfigManager>();
        
        // Subscribe to events
        GameConfigManager.OnConfigLoaded += OnConfigLoaded;
        GameConfigManager.OnConfigError += OnConfigError;
        
        // Check if configuration is already loaded
        if (configManager.IsConfigLoaded())
        {
            OnConfigLoaded();
        }
    }
    
    private void OnConfigLoaded()
    {
        Debug.Log("Configuration loaded!");
        
        // Apply game settings
        ApplyGameSettings();
        ApplyEconomySettings();
        ApplyContentSettings();
    }
    
    private void OnConfigError(string error)
    {
        Debug.LogError($"Configuration error: {error}");
        // Handle configuration error (show UI message, use default values, etc.)
    }
    
    private void ApplyGameSettings()
    {
        // Game state settings
        bool maintenanceMode = configManager.GetBool("state.maintenanceMode", false);
        bool pvpEnabled = configManager.GetBool("state.pvpEnabled", true);
        
        if (maintenanceMode)
        {
            // Show maintenance mode UI
            ShowMaintenanceScreen();
            return;
        }
        
        // Game configuration
        int maxPlayers = configManager.GetInt("config.maxPlayersPerMatch", 10);
        float expMultiplier = configManager.GetFloat("config.experienceMultiplier", 1.0f);
        
        Debug.Log($"Max players: {maxPlayers}, XP Multiplier: {expMultiplier}");
        
        // Apply settings to your game systems
        // MatchmakingSystem.SetMaxPlayers(maxPlayers);
        // ExperienceSystem.SetMultiplier(expMultiplier);
    }
    
    private void ApplyEconomySettings()
    {
        var economyRates = configManager.GetDictionary("data.economyRates");
        
        if (economyRates.ContainsKey("gold_to_gems"))
        {
            int goldToGems = Convert.ToInt32(economyRates["gold_to_gems"]);
            // EconomySystem.SetGoldToGemsRate(goldToGems);
        }
        
        var priceOverrides = configManager.GetDictionary("data.priceOverrides");
        
        if (priceOverrides.ContainsKey("premium_character"))
        {
            int characterPrice = Convert.ToInt32(priceOverrides["premium_character"]);
            // ShopSystem.SetCharacterPrice(characterPrice);
        }
    }
    
    private void ApplyContentSettings()
    {
        var enabledLevels = configManager.GetStringList("list.enabledLevels");
        var disabledItems = configManager.GetStringList("list.disabledItems");
        
        Debug.Log($"Enabled levels: {string.Join(", ", enabledLevels)}");
        Debug.Log($"Disabled items: {string.Join(", ", disabledItems)}");
        
        // Apply content filtering
        // LevelSystem.SetEnabledLevels(enabledLevels);
        // ItemSystem.SetDisabledItems(disabledItems);
    }
    
    private void ShowMaintenanceScreen()
    {
        // Show maintenance mode UI
        Debug.Log("Game is in maintenance mode");
    }
    
    private void OnDestroy()
    {
        // Unsubscribe from events
        GameConfigManager.OnConfigLoaded -= OnConfigLoaded;
        GameConfigManager.OnConfigError -= OnConfigError;
    }
}
```

### Advanced Usage with Callbacks

```csharp
using UnityEngine;

public class DynamicConfigLoader : MonoBehaviour
{
    private GameConfigManager configManager;
    
    private void Start()
    {
        configManager = FindObjectOfType<GameConfigManager>();
        
        // Fetch specific configuration keys on demand
        LoadPlayerSettings();
        LoadShopSettings();
    }
    
    private void LoadPlayerSettings()
    {
        configManager.FetchConfigurationKey("config.maxFriends", (value) =>
        {
            int maxFriends = Convert.ToInt32(value);
            Debug.Log($"Max friends: {maxFriends}");
            // SocialSystem.SetMaxFriends(maxFriends);
        });
    }
    
    private void LoadShopSettings()
    {
        configManager.FetchConfigurationKey("config.shopDiscountPercent", (value) =>
        {
            float discount = Convert.ToSingle(value) / 100f;
            Debug.Log($"Shop discount: {discount:P}");
            // ShopSystem.SetDiscountMultiplier(1f - discount);
        });
    }
}
```

## 3. Configuration Categories Example

```csharp
using UnityEngine;
using System.Collections.Generic;

public class CategoryConfigLoader : MonoBehaviour
{
    private GameConfigManager configManager;
    
    private void Start()
    {
        configManager = FindObjectOfType<GameConfigManager>();
        
        // Wait for config to load, then process categories
        GameConfigManager.OnConfigLoaded += ProcessConfigurationByCategory;
    }
    
    private void ProcessConfigurationByCategory()
    {
        var allKeys = configManager.GetAllKeys();
        
        var gameStateKeys = new List<string>();
        var configKeys = new List<string>();
        var listKeys = new List<string>();
        var dataKeys = new List<string>();
        var metaKeys = new List<string>();
        
        // Categorize keys
        foreach (string key in allKeys)
        {
            if (key.StartsWith("state."))
                gameStateKeys.Add(key);
            else if (key.StartsWith("config."))
                configKeys.Add(key);
            else if (key.StartsWith("list."))
                listKeys.Add(key);
            else if (key.StartsWith("data."))
                dataKeys.Add(key);
            else if (key.StartsWith("meta."))
                metaKeys.Add(key);
        }
        
        // Process each category
        ProcessGameState(gameStateKeys);
        ProcessConfiguration(configKeys);
        ProcessLists(listKeys);
        ProcessData(dataKeys);
        ProcessMetadata(metaKeys);
    }
    
    private void ProcessGameState(List<string> keys)
    {
        Debug.Log("Processing Game State:");
        
        foreach (string key in keys)
        {
            bool value = configManager.GetBool(key);
            string settingName = key.Replace("state.", "");
            Debug.Log($"  {settingName}: {value}");
            
            // Apply game state settings
            switch (settingName)
            {
                case "maintenanceMode":
                    // Handle maintenance mode
                    break;
                case "pvpEnabled":
                    // Enable/disable PvP
                    break;
                case "chatEnabled":
                    // Enable/disable chat
                    break;
            }
        }
    }
    
    private void ProcessConfiguration(List<string> keys)
    {
        Debug.Log("Processing Configuration:");
        
        foreach (string key in keys)
        {
            string settingName = key.Replace("config.", "");
            
            if (settingName.Contains("max") || settingName.Contains("Days") || settingName.Contains("MaxLength"))
            {
                int value = configManager.GetInt(key);
                Debug.Log($"  {settingName}: {value}");
            }
            else if (settingName.Contains("Multiplier") || settingName.Contains("Percent"))
            {
                float value = configManager.GetFloat(key);
                Debug.Log($"  {settingName}: {value}");
            }
            else
            {
                bool value = configManager.GetBool(key);
                Debug.Log($"  {settingName}: {value}");
            }
        }
    }
    
    private void ProcessLists(List<string> keys)
    {
        Debug.Log("Processing Lists:");
        
        foreach (string key in keys)
        {
            var list = configManager.GetStringList(key);
            string listName = key.Replace("list.", "");
            Debug.Log($"  {listName}: {string.Join(", ", list)}");
        }
    }
    
    private void ProcessData(List<string> keys)
    {
        Debug.Log("Processing Data Objects:");
        
        foreach (string key in keys)
        {
            var data = configManager.GetDictionary(key);
            string dataName = key.Replace("data.", "");
            Debug.Log($"  {dataName}:");
            
            foreach (var kvp in data)
            {
                Debug.Log($"    {kvp.Key}: {kvp.Value}");
            }
        }
    }
    
    private void ProcessMetadata(List<string> keys)
    {
        Debug.Log("Processing Metadata:");
        
        foreach (string key in keys)
        {
            string value = configManager.GetString(key);
            string metaName = key.Replace("meta.", "");
            Debug.Log($"  {metaName}: {value}");
        }
    }
}
```

## 4. Error Handling and Fallbacks

```csharp
using UnityEngine;

public class ConfigErrorHandler : MonoBehaviour
{
    private GameConfigManager configManager;
    
    [System.Serializable]
    public class DefaultConfig
    {
        public bool maintenanceMode = false;
        public bool pvpEnabled = true;
        public int maxPlayersPerMatch = 10;
        public float experienceMultiplier = 1.0f;
        public int energyRegenMinutes = 5;
    }
    
    [SerializeField] private DefaultConfig defaultConfig = new DefaultConfig();
    
    private void Start()
    {
        configManager = FindObjectOfType<GameConfigManager>();
        
        GameConfigManager.OnConfigError += HandleConfigError;
        GameConfigManager.OnConfigLoaded += HandleConfigLoaded;
    }
    
    private void HandleConfigError(string error)
    {
        Debug.LogWarning($"Using fallback configuration due to: {error}");
        ApplyFallbackConfiguration();
    }
    
    private void HandleConfigLoaded()
    {
        Debug.Log("Remote configuration loaded successfully");
    }
    
    private void ApplyFallbackConfiguration()
    {
        // Apply default configuration values
        // MatchmakingSystem.SetMaxPlayers(defaultConfig.maxPlayersPerMatch);
        // ExperienceSystem.SetMultiplier(defaultConfig.experienceMultiplier);
        // EnergySystem.SetRegenTime(defaultConfig.energyRegenMinutes);
        
        Debug.Log("Fallback configuration applied");
    }
    
    private void OnDestroy()
    {
        GameConfigManager.OnConfigError -= HandleConfigError;
        GameConfigManager.OnConfigLoaded -= HandleConfigLoaded;
    }
}
```

## 5. Testing Configuration in Unity

### Configuration Test Script

```csharp
using UnityEngine;

public class ConfigTester : MonoBehaviour
{
    private GameConfigManager configManager;
    
    [ContextMenu("Test All Configuration Types")]
    public void TestAllConfigurationTypes()
    {
        configManager = FindObjectOfType<GameConfigManager>();
        
        if (!configManager.IsConfigLoaded())
        {
            Debug.LogWarning("Configuration not loaded yet");
            return;
        }
        
        Debug.Log("=== Configuration Test ===");
        
        // Test booleans
        Debug.Log($"maintenanceMode: {configManager.GetBool("state.maintenanceMode")}");
        Debug.Log($"pvpEnabled: {configManager.GetBool("state.pvpEnabled")}");
        
        // Test integers
        Debug.Log($"maxPlayersPerMatch: {configManager.GetInt("config.maxPlayersPerMatch")}");
        Debug.Log($"energyRegenMinutes: {configManager.GetInt("config.energyRegenMinutes")}");
        
        // Test floats
        Debug.Log($"experienceMultiplier: {configManager.GetFloat("config.experienceMultiplier")}");
        
        // Test strings
        Debug.Log($"apiVersion: {configManager.GetString("meta.apiVersion")}");
        
        // Test lists
        var enabledLevels = configManager.GetStringList("list.enabledLevels");
        Debug.Log($"enabledLevels: {string.Join(", ", enabledLevels)}");
        
        // Test dictionaries
        var economyRates = configManager.GetDictionary("data.economyRates");
        Debug.Log("economyRates:");
        foreach (var kvp in economyRates)
        {
            Debug.Log($"  {kvp.Key}: {kvp.Value}");
        }
        
        Debug.Log("=== End Test ===");
    }
}
```

## 6. WebGL Considerations

For WebGL builds, you may need to handle CORS and security considerations:

```csharp
#if UNITY_WEBGL
using UnityEngine;
using UnityEngine.Networking;
using System.Collections;

public class WebGLConfigManager : MonoBehaviour
{
    [SerializeField] private string gameID = "your_game_id";
    [SerializeField] private string apiKey = "your_api_key";
    [SerializeField] private string apiBaseUrl = "https://your-domain.com/api/v1/config/";
    
    public void FetchConfiguration()
    {
        StartCoroutine(FetchConfigurationCoroutine());
    }
    
    private IEnumerator FetchConfigurationCoroutine()
    {
        string url = $"{apiBaseUrl}{gameID}?api_key={apiKey}";
        
        using (UnityWebRequest request = UnityWebRequest.Get(url))
        {
            // Add CORS headers for WebGL
            request.SetRequestHeader("Access-Control-Allow-Origin", "*");
            request.SetRequestHeader("Access-Control-Allow-Headers", "Content-Type, X-API-Key");
            
            yield return request.SendWebRequest();
            
            if (request.result == UnityWebRequest.Result.Success)
            {
                Debug.Log("WebGL configuration loaded: " + request.downloadHandler.text);
                // Process configuration
            }
            else
            {
                Debug.LogError("WebGL configuration error: " + request.error);
            }
        }
    }
}
#endif
```

## 7. Performance Optimization

### Configuration Caching

```csharp
using UnityEngine;
using System.Collections.Generic;
using System.IO;

public class ConfigCache : MonoBehaviour
{
    private GameConfigManager configManager;
    private string cacheFilePath;
    
    private void Start()
    {
        configManager = FindObjectOfType<GameConfigManager>();
        cacheFilePath = Path.Combine(Application.persistentDataPath, "config_cache.json");
        
        // Try to load from cache first
        LoadFromCache();
        
        // Subscribe to config loaded event to save to cache
        GameConfigManager.OnConfigLoaded += SaveToCache;
    }
    
    private void LoadFromCache()
    {
        if (File.Exists(cacheFilePath))
        {
            try
            {
                string cachedData = File.ReadAllText(cacheFilePath);
                // Apply cached configuration immediately for faster startup
                Debug.Log("Loaded configuration from cache");
            }
            catch (System.Exception e)
            {
                Debug.LogWarning($"Failed to load cache: {e.Message}");
            }
        }
    }
    
    private void SaveToCache()
    {
        try
        {
            // Save current configuration to cache
            Debug.Log("Configuration saved to cache");
        }
        catch (System.Exception e)
        {
            Debug.LogWarning($"Failed to save cache: {e.Message}");
        }
    }
}
```

This comprehensive Unity integration guide provides everything developers need to connect their Unity games with the Game Configuration API, including basic usage, advanced features, error handling, and performance optimization techniques.