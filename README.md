# Game Configuration API

A PHP-based API for managing game configurations with an admin dashboard, designed for Unity games and compatible with cPanel hosting.

## Features

- RESTful API for fetching game configurations
- Admin dashboard for managing games and configurations
- Database-driven configuration storage with active/inactive states
- Secure authentication for admin access
- Automatic database initialization
- Support for multiple input methods (GET, POST, JSON body)
- cPanel compatible structure
- Configurable logging to prevent storage overflow

## Installation

1. Upload all files to your web server via FTP or cPanel File Manager
2. Create a MySQL database and user in cPanel's MySQL Database section
3. Set environment variables in cPanel (recommended):
   - Go to cPanel → Software → Select PHP Version → Environment Variables
   - OR, if that's not available, create a `.env` file in the root directory
4. If using `.env` file, copy `.env.example` to `.env` and update the configuration values:
   - `DB_HOST` - Your database host (usually localhost)
   - `DB_USER` - Your database username
   - `DB_PASS` - Your database password
   - `DB_NAME` - Your database name
   - `ADMIN_USERNAME` - Admin username for the dashboard
   - `ADMIN_PASSWORD` - Admin password (will be hashed automatically)
   - `ENABLE_API_LOGGING` - Set to 'true' to enable API request logging, 'false' to disable (default: true)
   - `ENABLE_SECURITY_LOGGING` - Set to 'true' to enable security event logging, 'false' to disable (default: true)
5. The database tables will be created automatically when you first access the API or admin panel
6. Access the admin dashboard at `/admin/login.php`

### Setting Environment Variables in cPanel (Recommended for Security)

For enhanced security in cPanel:

1. Log into your cPanel dashboard
2. Go to **Software** section → **Select PHP Version**
3. Click the **Environment Variables** button (top right)
4. Add the following variables:
   - **Name**: `DB_HOST`, **Value**: your database host (usually `localhost`)
   - **Name**: `DB_USER`, **Value**: your database username
   - **Name**: `DB_PASS`, **Value**: your database password
   - **Name**: `DB_NAME`, **Value**: your database name
   - **Name**: `ADMIN_USERNAME`, **Value**: your chosen admin username
   - **Name**: `ADMIN_PASSWORD`, **Value**: your chosen admin password
   - **Name**: `BASE_URL`, **Value**: base URL if deploying to subdirectory (e.g., `https://yoursite.com/subdir` if deployed to `yoursite.com/subdir/`)
   - **Name**: `ENABLE_API_LOGGING`, **Value**: 'true' or 'false' to enable/disable API request logging (default: 'true')
   - **Name**: `ENABLE_SECURITY_LOGGING`, **Value**: 'true' or 'false' to enable/disable security event logging (default: 'true')
5. Click **Add** for each variable, then **Save** when done

### Deploying to Subdirectories or Subdomains

The system is designed to work with subdomains and subdirectories:

- For subdomain deployment (e.g., `https://config.yoursite.com/`), no special configuration needed
- For subdirectory deployment (e.g., `https://yoursite.com/gameconfig/`), set the `BASE_URL` environment variable
- Relative paths are used throughout the system to ensure proper functionality in any deployment scenario

## Default Admin Credentials

- Username: `admin`
- Password: `password123`

**Important:** Change the default password immediately after installation!

## API Usage

### Get Game Configuration (Secure API Key Authentication)

```
GET /api/index.php?api_key=YOUR_API_KEY
```

or with X-API-Key header (recommended for security):

```
GET /api/index.php
X-API-Key: YOUR_API_KEY
```

or with POST:

```
POST /api/index.php
Content-Type: application/x-www-form-urlencoded

api_key=YOUR_API_KEY
```

or with JSON:

```
POST /api/index.php
Content-Type: application/json

{
  "api_key": "YOUR_API_KEY"
}
```

### Example Response

```json
{
  "success": true,
  "config": {
    "difficulty": "normal",
    "max_players": "4",
    "server_url": "https://api.example.com"
  }
}
```

## Admin Dashboard

- Login page: `/admin/login.php`
- Dashboard: `/admin/index.php`
- Manage games: `/admin/games.php`
- Manage configurations: `/admin/configs.php`

## Security Notes

- Change the default admin password after installation
- For production, set ADMIN_PASSWORD via environment variable rather than using defaults
- The API checks for active games and configurations before returning them
- Input validation and sanitization is performed
- Rate limiting prevents API abuse and spamming
- CSRF protection added to all admin forms
- XSS protection with output sanitization
- Proper session management with security cookie settings
- SQL injection prevention with PDO prepared statements
- Rate limiting and IP-based security checks
- Consider restricting access to the admin panel via IP whitelisting
- Use HTTPS in production environments
- Validate and sanitize all inputs
- CORS policy configured with specific allowed origins (set in ALLOWED_ORIGINS environment variable)
- Configurable logging to prevent storage overflow (see logging section below)

### Logging Configuration

The system supports configurable logging to help prevent storage overflow when running multiple APIs on the same hosting account:

- `ENABLE_API_LOGGING`: Set to 'true' to log API requests, 'false' to disable (default: true)
- `ENABLE_SECURITY_LOGGING`: Set to 'true' to log security events (rate limits, failed logins, etc.), 'false' to disable (default: true)

When running multiple APIs that share storage space, you can disable logging for lower-priority APIs by setting these variables to 'false'. This helps conserve storage space while keeping critical APIs functional.

### Configuring Allowed Origins (CORS)

When running in production, you need to configure the `ALLOWED_ORIGINS` environment variable to specify which domains can access your API. Examples for .env file:

```
# Single domain
ALLOWED_ORIGINS=https://yourdomain.com

# Multiple domains (comma-separated)
ALLOWED_ORIGINS=https://yourdomain.com,https://subdomain.yourdomain.com,https://api.yourdomain.com

# With WWW and non-WWW
ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com

# For development environments
ALLOWED_ORIGINS=https://localhost,https://127.0.0.1

# Production with multiple subdomains and main domain
ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com,https://api.yourdomain.com,https://config.yourdomain.com,https://staging.yourdomain.com
```

**Important:** Always specify exact domains in production. Avoid using wildcards unless absolutely necessary, as they can introduce security risks.

## For Unity Games

To fetch configurations in your Unity game:

```csharp
using System.Collections;
using UnityEngine;

public class ConfigManager : MonoBehaviour
{
    [SerializeField] private string apiKey = "your-api-key";
    [SerializeField] private string apiUrl = "https://yoursite.com/api/index.php";

    private async void Start()
    {
        var config = await GetGameConfig();
        if (config != null)
        {
            // Use your configurations
            Debug.Log("Difficulty: " + config["difficulty"]);
        }
    }

    private async Task<Dictionary<string, string>> GetGameConfig()
    {
        var request = UnityWebRequest.Get($"{apiUrl}?api_key={apiKey}");
        var op = request.SendWebRequest();

        while (!op.isDone) await Task.Yield();

        if (request.result == UnityWebRequest.Result.Success)
        {
            var json = request.downloadHandler.text;
            var response = JsonUtility.FromJson<ApiResponse>(json);
            if (response.success)
            {
                return response.config;
            }
        }
        return null;
    }
    
    [System.Serializable]
    private class ApiResponse
    {
        public bool success;
        public Dictionary<string, string> config;
    }
}
```

## Project Structure

```
GameConfigurationAPI/
├── api/                 # API endpoints
│   ├── index.php       # Main API endpoint
│   ├── config.php      # Database connection and initialization
│   └── functions.php   # Helper functions
├── admin/              # Admin dashboard
│   ├── index.php       # Dashboard home
│   ├── login.php       # Login page
│   ├── logout.php      # Logout functionality
│   ├── games.php       # Game management
│   ├── configs.php     # Configuration management
│   └── includes/       # Common includes (header.php, sidebar.php, functions.php)
├── database/           # Database setup files
│   └── setup.sql       # Database schema (for reference - tables auto-created)
├── config.php          # Main configuration file
├── .env                # Environment variables (not in version control)
├── .env.example        # Example environment variables file
├── .htaccess           # Security and access rules
├── test.html           # Frontend test page
├── test_system.php     # Backend test script
└── README.md           # This file
```

## Testing

To test the system functionality:
1. Use the visual test page at `/test.html`
2. Run the backend test script at `/test_system.php` (for developers)

## Troubleshooting

- If you get "Database connection failed" errors, check your database configuration in `.env` or environment variables
- Make sure the database user has appropriate permissions (CREATE, SELECT, INSERT, UPDATE, DELETE)
- Ensure all required PHP extensions are installed (PDO, MySQL, JSON)
- Check that the database tables were created automatically by accessing the API or admin panel
- If you get "Game not found" errors, ensure the game exists and is active in the admin panel
- If you get "Configuration not found" errors, ensure configurations are added and marked as active
- If logs are filling storage space, consider setting `ENABLE_API_LOGGING` or `ENABLE_SECURITY_LOGGING` to 'false' in your environment variables