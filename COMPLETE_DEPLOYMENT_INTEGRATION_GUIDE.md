# COMPLETE DEPLOYMENT & INTEGRATION GUIDE

## Quick Start Overview

This guide will help you deploy your Game Configuration API and integrate it with Unity games. The system is designed to be **drag-and-drop ready** with minimal configuration required and works with both **main domain** and **subdomain** deployments.

## 🚀 SUPER SIMPLE DEPLOYMENT (3 Steps)

### Choose Your Deployment Method

You can deploy the API in two ways:

1. **Main Domain** (e.g., `yourdomain.com/game-api/`)
2. **Subdomain** (e.g., `api.yourdomain.com/`)

Both methods work exactly the same - just choose the one you prefer!

### Step 1: Create Database

1. **Log in to cPanel**
   - Go to `yourdomain.com/cpanel`
   - Enter your username and password

2. **Create Database**
   - Find the "Databases" section
   - Click on "MySQL Databases"
   - Under "Create New Database", type: `game_config_api`
   - Click "Create Database"

3. **Create Database User**
   - Scroll down to "MySQL Users"
   - Under "Add New User", type:
     - Username: `gameapi_user`
     - Password: Click "Password Generator" and save the generated password
   - Click "Create User"

4. **Add User to Database**
   - Scroll down to "Add User to Database"
   - Select your user: `gameapi_user`
   - Select your database: `game_config_api`
   - Check "ALL PRIVILEGES"
   - Click "Make Changes"

### Step 2: Import Database Tables

1. **Open phpMyAdmin**
   - In cPanel, find "Databases" section
   - Click on "phpMyAdmin"

2. **Select Your Database**
   - On the left side, click on `game_config_api`

3. **Import All Tables**
   - Click on the "Import" tab at the top
   - Click "Choose File"
   - Select the `sql/schema.sql` file from your project
   - Leave all settings as default
   - Click "Go" at the bottom

4. **Import Security Table**
   - Click "Import" again
   - Select the `sql/login_attempts_table.sql` file
   - Click "Go"

5. **Import Test Data (Optional)**
   - Click "Import" again
   - Select the `sql/test_data.sql` file
   - Click "Go"

### Step 3A: Main Domain Deployment

1. **Open File Manager**
   - In cPanel, find "Files" section
   - Click on "File Manager"

2. **Create Project Folder**
   - Navigate to `public_html` folder
   - Click "+ Folder" at the top
   - Folder Name: `game-api`
   - Click "Create Folder"

3. **Upload All Files**
   - Click on the `game-api` folder to enter it
   - Click "Upload" at the top
   - Drag ALL your project files into the upload area
   - Wait for all files to upload

### Step 3B: Subdomain Deployment

1. **Create Subdomain**
   - In cPanel, find "Domains" section
   - Click on "Subdomains"
   - Under "Create Subdomain":
     - Subdomain: `api`
     - Domain: Select your domain from dropdown
     - Document Root: `public_html/api` (cPanel will create this)
   - Click "Create"

2. **Open File Manager**
   - In cPanel, find "Files" section
   - Click on "File Manager"

3. **Upload All Files**
   - Navigate to the `api` folder in `public_html`
   - Click "Upload" at the top
   - Drag ALL your project files into the upload area
   - Wait for all files to upload

### Complete Configuration (Both Methods)

1. **Create Configuration File**
   - In File Manager, find `.env.example`
   - Right-click and choose "Copy"
   - New Name: `.env`
   - Click "Copy File"

2. **Edit Configuration**
   - Right-click on `.env` and choose "Edit"
   - Update ONLY these settings:
     ```
     DB_NAME=game_config_api
     DB_USERNAME=gameapi_user
     DB_PASSWORD=your_saved_password_here
     JWT_SECRET=your-unique-secret-key-32-chars-min
     ```
   - Click "Save Changes"

3. **Set File Permissions**
   - Select the `.env` file
   - Right-click and choose "Change Permissions"
   - Set permissions to `600`
   - Click "Change Permissions"

**🎉 THAT'S IT! Your API is now live!**

## 📍 Access Your API

### Main Domain Deployment
- **API**: `https://yourdomain.com/game-api/api/v1/config/`
- **Admin Panel**: `https://yourdomain.com/game-api/admin/`

### Subdomain Deployment
- **API**: `https://api.yourdomain.com/api/v1/config/`
- **Admin Panel**: `https://api.yourdomain.com/admin/`

## ✅ What's Automatically Configured

You DON'T need to configure these - they're handled automatically:

- ✅ HTTPS redirection (once SSL is installed)
- ✅ Security headers
- ✅ CORS settings for Unity WebGL
- ✅ PHP settings (memory, execution time, etc.)
- ✅ Directory creation (cache, rate_limits, logs)
- ✅ Error handling
- ✅ Gzip compression
- ✅ File permissions protection
- ✅ Exploit blocking
- ✅ Both main domain and subdomain compatibility

## 🔧 Optional Enhancements

### Enable SSL (Recommended)

1. **Install SSL Certificate**
   - In cPanel, find "Security" section
   - Click on "SSL/TLS Status"
   - Find your domain/subdomain
   - Click "Run AutoSSL"

### Set PHP Version (Optional)

1. **Set PHP Version**
   - In cPanel, find "Software" section
   - Click on "MultiPHP Manager"
   - Find your folder (`game-api` or `api`)
   - Select PHP version `8.0` or higher
   - Click "Apply"

## 🎮 Unity Integration

### Step 1: Create New Unity Project

1. **Open Unity Hub**
   - Download and install Unity Hub if you haven't already
   - Sign in with your Unity account

2. **Create New Project**
   - Click "New project"
   - Select "3D" or "2D" template
   - Project Name: Your Game Name
   - Click "Create Project"

### Step 2: Install JSON Package

1. **Open Package Manager**
   - In Unity, go to "Window" → "Package Manager"
   - Click the "+" icon in the top-left
   - Select "Add package from git URL"

2. **Install Newtonsoft.Json**
   - Paste this URL: `https://github.com/JamesNK/Newtonsoft.Json.git`
   - Click "Add"
   - Wait for installation to complete

### Step 3: Create Configuration Manager

1. **Create Script**
   - In Unity Project window, right-click
   - Select "Create" → "C# Script"
   - Name it: `GameConfigManager`

2. **Open Script**
   - Double-click on the script to open it
   - Replace all content with this:

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
    [SerializeField] private string apiBaseUrl = "https://yourdomain.com/game-api/api/v1/config/"; // Change for subdomain
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

3. **Save Script**
   - Press Ctrl+S (or Cmd+S on Mac) to save

### Step 4: Create Game Manager

1. **Create Script**
   - Right-click in Project window
   - Select "Create" → "C# Script"
   - Name it: `GameManager`

2. **Open Script**
   - Double-click to open it
   - Replace all content with this:

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

3. **Save Script**
   - Press Ctrl+S (or Cmd+S on Mac) to save

### Step 5: Set Up Unity Scene

1. **Create Empty Game Objects**
   - In Unity, right-click in Hierarchy window
   - Select "Create Empty"
   - Name one: `ConfigurationManager`
   - Create another empty object: `GameManager`

2. **Add Scripts**
   - Select `ConfigurationManager` object
   - Click "Add Component" in Inspector
   - Search for `GameConfigManager` and add it
   - Select `GameManager` object
   - Add `GameManager` script

3. **Configure API Settings**
   - Select `ConfigurationManager` object
   - In Inspector, find "API Configuration" section
   - Set Game ID to: `my_game_001` (or whatever you used)
   - Set API Key to: the API key you copied from admin panel
   - Set API Base URL to:
     - **Main Domain**: `https://yourdomain.com/game-api/api/v1/config/`
     - **Subdomain**: `https://api.yourdomain.com/api/v1/config/`

### Step 6: Test Configuration

1. **Run Scene**
   - Click the Play button at the top of Unity
   - Look at the Console window (Window → General → Console)

2. **Check Results**
   - You should see messages like: "Max players: 10, XP Multiplier: 1.0"
   - If you see errors, check the API key and game ID

## 🔐 Admin Panel Setup

### Step 1: Access Admin Panel

1. **Open Admin Panel**
   - **Main Domain**: `https://yourdomain.com/game-api/admin/`
   - **Subdomain**: `https://api.yourdomain.com/admin/`
   - You should see a login page

2. **Login**
   - Username: `admin`
   - Password: `admin123`
   - Click "Login"

### Step 2: Change Admin Password

1. **Go to Profile**
   - After logging in, look for "Profile" or "Account" option
   - Click on it

2. **Change Password**
   - Find "Password" section
   - Enter your new secure password
   - Confirm the password
   - Click "Save Changes"

### Step 3: Create Your First Game

1. **Go to Games Section**
   - In the admin panel, click on "Games"
   - Click "Add New Game" or "Create Game"

2. **Fill Game Details**
   - Name: Your Game Name (e.g., "Space Warriors")
   - Game ID: `my_game_001` (unique identifier, no spaces)
   - Description: Brief description of your game
   - Status: Keep as "Active"
   - Click "Create Game" or "Save"

3. **Copy API Key**
   - After creating the game, you'll see an API key
   - Click "Copy" or write it down
   - You'll need this for Unity integration

### Step 4: Add Sample Configuration

1. **Go to Configurations**
   - Click on "Configurations" in the admin panel
   - Click "Add Configuration" or "Create New"

2. **Fill Configuration Details**
   - Game: Select your game from the dropdown
   - Key: `state.maintenanceMode`
   - Value: `false`
   - Data Type: `boolean`
   - Category: `state`
   - Description: "Whether the game is in maintenance mode"
   - Click "Save" or "Create Configuration"

## 🧪 Testing Your API

### Test in Browser

1. **Open Browser**
   - **Main Domain**: `https://yourdomain.com/game-api/api/v1/config/my_game_001`
   - **Subdomain**: `https://api.yourdomain.com/api/v1/config/my_game_001`

2. **Expected Result**
   - You should see an error message about API key (this is normal)
   - It means the API is working but needs authentication

### Test with Postman (Optional)

1. **Install Postman**
   - Download from: https://www.postman.com/downloads/
   - Create a free account

2. **Create Request**
   - Open Postman
   - Click "New" → "Request"
   - Method: GET
   - URL:
     - **Main Domain**: `https://yourdomain.com/game-api/api/v1/config/my_game_001`
     - **Subdomain**: `https://api.yourdomain.com/api/v1/config/my_game_001`
   - Click "Headers"
   - Add new header:
     - Key: `X-API-Key`
     - Value: your_api_key_here
   - Click "Send"

3. **Expected Result**
   - You should see your configuration data in JSON format

## 🔒 Security Checklist

Before going live, make sure you:

- [ ] Changed the default admin password
- [ ] HTTPS is enabled and working
- [ ] API keys are kept secure (don't share them)
- [ ] Database is backed up regularly

## 🐛 Common Issues and Solutions

### Database Connection Errors
- **Problem**: "Database connection failed"
- **Solution**: Check your `.env` file for correct database credentials
- **Remember**: cPanel often adds prefixes to database names

### API Key Not Working
- **Problem**: "Invalid API key" error
- **Solution**: 
  1. Check if you copied the full API key
  2. Make sure the game status is 'active' in admin panel
  3. Verify there are no extra spaces in the API key

### Unity Configuration Errors
- **Problem**: Configuration not loading in Unity
- **Solution**:
  1. Check the Console window for error messages
  2. Verify the API URL is correct
  3. Make sure you're using the right Game ID

### CORS Errors (WebGL Builds)
- **Problem**: CORS errors in browser console
- **Solution**: The .htaccess file already handles CORS for Unity WebGL

### 403 Errors (Subdomain)
- **Problem**: Getting 403 errors on subdomain deployment
- **Solution**: The .htaccess file is already configured for subdomain compatibility
- **Check**: Make sure all files are uploaded to the correct subdomain directory

### Directory Creation Errors
- **Problem**: Errors about missing directories
- **Solution**: The application creates required directories automatically
- **Note**: You don't need to create any directories manually

## 🎯 Next Steps

Congratulations! Your Game Configuration API is now running with minimal setup. Here's what you can do next:

1. **Add More Configurations**
   - Log in to admin panel
   - Add more game settings and configurations

2. **Update Games Remotely**
   - Change configurations in admin panel
   - Your Unity games will automatically pick up changes

3. **Monitor Usage**
   - Keep an eye on API usage
   - Check for any unusual activity

4. **Regular Maintenance**
   - Update admin password regularly
   - Keep backups of your database

That's it! You now have a fully functional Game Configuration API that was truly **drag-and-drop ready** with automatic configuration handling and works with both main domain and subdomain deployments.