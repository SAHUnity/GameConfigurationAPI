# Game Configuration API

A PHP-based API for managing game configurations with an admin dashboard, designed for Unity games and compatible with cPanel hosting.

## Features

- RESTful API for fetching game configurations
- Admin dashboard for managing games and configurations
- Database-driven configuration storage
- Secure authentication for admin access
- cPanel compatible structure

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
5. Run the SQL from `database/setup.sql` to create the required tables
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

### Get Game Configuration

```
GET /api/index.php?game_id=GAME_ID
```

or

```
GET /api/index.php?slug=GAME_SLUG
```

### Example Response

```json
{
  "success": true,
  "game_id": 1,
  "slug": "sample-game",
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
- For production, set ADMIN_PASSWORD via environment variable rather than hardcoding
- Consider restricting access to the admin panel via IP whitelisting
- Use HTTPS in production environments
- Validate and sanitize all inputs

## For Unity Games

To fetch configurations in your Unity game:

```csharp
using System.Collections;
using UnityEngine;

public class ConfigManager : MonoBehaviour
{
    [SerializeField] private string gameId = "your-game-slug";
    [SerializeField] private string apiUrl = "https://yoursite.com/api/index.php";

    private async void Start()
    {
        var config = await GetGameConfig(gameId);
        if (config != null)
        {
            // Use your configurations
            Debug.Log("Difficulty: " + config["difficulty"]);
        }
    }

    private async Task<Dictionary<string, string>> GetGameConfig(string gameSlug)
    {
        var request = UnityWebRequest.Get($"{apiUrl}?slug={gameSlug}");
        var op = request.SendWebRequest();

        while (!op.isDone) await Task.Yield();

        if (request.result == UnityWebRequest.Result.Success)
        {
            var json = request.downloadHandler.text;
            // Parse JSON and return config dictionary
            // Implementation depends on your JSON parsing solution
        }
        return null;
    }
}
```

## Project Structure

```
GameConfigurationAPI/
├── api/                 # API endpoints
│   ├── index.php       # Main API endpoint
│   ├── config.php      # Database connection
│   └── functions.php   # Helper functions
├── admin/              # Admin dashboard
│   ├── index.php       # Dashboard home
│   ├── login.php       # Login page
│   ├── logout.php      # Logout functionality
│   ├── games.php       # Game management
│   ├── configs.php     # Configuration management
│   ├── assets/         # CSS, JS, Images
│   └── includes/       # Common includes
├── database/           # Database setup files
│   └── setup.sql       # Database schema
├── config.php          # Main configuration file
└── test.html           # Test page
```

## Troubleshooting

- If you get "Database connection failed" errors, check your database configuration in `config.php`
- Make sure the database user has appropriate permissions
- Ensure all required PHP extensions are installed (PDO, MySQL)