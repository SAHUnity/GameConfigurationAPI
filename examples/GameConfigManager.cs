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
    [SerializeField] private string apiBaseUrl = "https://your-domain.com/game-api/api/v1/config/";
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
    
    /// <summary>
    /// Prints all configuration data to console (for debugging)
    /// </summary>
    [ContextMenu("Print All Configuration")]
    public void PrintAllConfiguration()
    {
        if (!isConfigLoaded)
        {
            Debug.LogWarning("Configuration not loaded yet");
            return;
        }
        
        Debug.Log("=== Configuration Data ===");
        foreach (var kvp in configData)
        {
            Debug.Log($"{kvp.Key}: {kvp.Value}");
        }
        Debug.Log("=== End Configuration ===");
    }
}