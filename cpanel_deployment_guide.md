# cPanel Deployment Guide - Game Configuration API

## Overview

This guide provides step-by-step instructions for deploying the Game Configuration API on a cPanel hosting environment. The process covers database setup, file uploads, configuration, and security considerations.

## Prerequisites

- cPanel hosting account with PHP 7.4+ (preferably 8.0+)
- MySQL/MariaDB database access
- File manager access or FTP/SFTP access
- Ability to create subdomains (optional but recommended)

## 1. Database Setup

### 1.1 Create Database via cPanel

1. Log in to your cPanel account
2. Navigate to **Databases** → **MySQL Databases**
3. Create a new database:
   - **New Database**: `game_config_api`
   - Click **Create Database**

### 1.2 Create Database User

1. In the same **MySQL Databases** section
2. Scroll down to **MySQL Users**
3. Create a new user:
   - **Username**: `gameapi_user` (or your preferred name)
   - **Password**: Generate a strong password (save it for later)
   - Click **Create User**

### 1.3 Add User to Database

1. In the **Add User to Database** section
2. Select your database and user
3. Click **Add**
4. Grant **ALL PRIVILEGES** on the next page
5. Click **Make Changes**

### 1.4 Import Database Schema

1. Navigate to **Databases** → **phpMyAdmin**
2. Select your database from the left sidebar
3. Click the **Import** tab
4. Upload the `sql/schema.sql` file from your project
5. Click **Go** to execute the SQL

### 1.5 Verify Database Setup

You should see these tables in your database:
- `games`
- `configurations`
- `admin_users`

## 2. File Upload

### 2.1 Prepare Files

1. Ensure all files from your project are ready for upload
2. Check that file permissions are appropriate (most files should be 644, directories 755)

### 2.2 Upload Methods

#### Method A: cPanel File Manager

1. Log in to cPanel
2. Navigate to **Files** → **File Manager**
3. Navigate to `public_html` directory
4. Create a new folder (recommended): `game-api`
5. Upload all project files to this directory

#### Method B: FTP/SFTP

1. Use an FTP client like FileZilla
2. Connect to your hosting account
3. Navigate to `public_html`
4. Create `game-api` directory
5. Upload all files

### 2.3 Set File Permissions

After uploading, set these permissions:

```bash
# Directories
755 (drwxr-xr-x)

# PHP files
644 (-rw-r--r--)

# Writable directories (if needed)
755 with proper ownership

# Configuration files
644 (or 600 for extra security)
```

## 3. Configuration

### 3.1 Database Configuration

Edit `config/database.php`:

```php
<?php
class Database {
    private $host = 'localhost'; // Usually localhost on cPanel
    private $db_name = 'your_cpanelprefix_game_config_api'; // Include cPanel prefix
    private $username = 'your_cpanelprefix_gameapi_user'; // Include cPanel prefix
    private $password = 'your_database_password'; // The password you created
    private $charset = 'utf8mb4';
    
    // ... rest of the file remains the same
}
?>
```

**Note**: cPanel typically adds a prefix to database names and usernames. Check the exact names in cPanel → MySQL Databases.

### 3.2 Application Configuration

Edit `config/config.php`:

```php
<?php
// Application settings
define('APP_NAME', 'Game Configuration API');
define('APP_VERSION', '1.0.0');
define('API_VERSION', 'v1');

// Security settings
define('JWT_SECRET', 'your-unique-secret-key-here'); // Change this!
define('SESSION_LIFETIME', 1800); // 30 minutes
define('RATE_LIMIT_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60); // seconds

// API Settings
define('CORS_ALLOWED_ORIGINS', '*'); // Restrict to your game domains in production
define('API_RESPONSE_FORMAT', 'json');

// File paths
define('ROOT_PATH', dirname(__DIR__));
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('CACHE_PATH', ROOT_PATH . '/cache');
define('RATE_LIMIT_PATH', ROOT_PATH . '/rate_limits');
?>
```

### 3.3 Create Writable Directories

Create these directories and set permissions:

```bash
# In your game-api directory
mkdir cache
mkdir rate_limits

# Set permissions (via File Manager or FTP)
chmod 755 cache
chmod 755 rate_limits
```

## 4. Web Server Configuration

### 4.1 .htaccess Setup

The `.htaccess` file should already be included in your project. Ensure it's uploaded to the root of your `game-api` directory.

### 4.2 PHP Version Check

1. In cPanel, navigate to **Software** → **MultiPHP Manager**
2. Select your `game-api` directory
3. Set PHP version to 7.4 or higher (preferably 8.0+)
4. Click **Apply**

### 4.3 PHP Extensions Required

Ensure these PHP extensions are enabled:
- PDO
- PDO MySQL
- JSON
- cURL
- mbstring
- OpenSSL

Check extensions in cPanel → **Software** → **MultiPHP INI Editor**.

## 5. Optional: Subdomain Setup

### 5.1 Create Subdomain

1. In cPanel, navigate to **Domains** → **Subdomains**
2. Create a new subdomain:
   - **Subdomain**: `api`
   - **Domain**: `yourdomain.com`
   - **Document Root**: `/public_html/game-api`

### 5.2 Update API Base URL

If using a subdomain, update your Unity integration to use:
```
https://api.yourdomain.com/api/v1/config/
```

## 6. Security Configuration

### 6.1 Restrict Directory Access

Add these `.htaccess` files to restrict access:

#### For `config/` directory:

```apache
# Deny access to configuration files
<Files "*.php">
    Require all denied
</Files>
```

#### For `includes/` directory:

```apache
# Deny access to include files
<Files "*.php">
    Require all denied
</Files>
```

#### For `cache/` and `rate_limits/` directories:

```apache
# Deny all access
Require all denied
```

### 6.2 Update CORS Settings

For production, restrict CORS in `config/config.php`:

```php
// Instead of '*', specify your game domains
define('CORS_ALLOWED_ORIGINS', 'https://yourgame.com,https://your-other-game.com');
```

### 6.3 IP Whitelisting (Optional)

Add to your `.htaccess` for admin access:

```apache
# Restrict admin access to specific IPs
<Files "admin/*">
    Require ip 192.168.1.1
    Require ip 203.0.113.0/24
</Files>
```

## 7. Testing the Installation

### 7.1 Test API Endpoint

Open your browser and navigate to:
```
https://yourdomain.com/game-api/api/v1/config/testgame
```

You should see a JSON response:
```json
{
    "success": false,
    "error": {
        "code": "INVALID_API_KEY",
        "message": "The provided API key is invalid or expired"
    }
}
```

This error is expected since we haven't created any games yet.

### 7.2 Test Admin Panel

Navigate to:
```
https://yourdomain.com/game-api/admin/
```

You should see the admin login page. Login with:
- Username: `admin`
- Password: `admin123`

**Important**: Change the default admin password immediately after first login!

## 8. First-Time Setup

### 8.1 Create Your First Game

1. Log in to the admin panel
2. Navigate to **Games** → **Add New Game**
3. Fill in the details:
   - **Name**: Your Game Name
   - **Game ID**: `my_game_001` (unique identifier)
   - **Description**: Game description
4. Click **Create Game**
5. Copy the generated API key for your Unity integration

### 8.2 Add Sample Configuration

1. In the admin panel, navigate to **Configurations**
2. Select your game
3. Add sample configurations:
   - **Key**: `state.maintenanceMode`
   - **Value**: `false`
   - **Data Type**: `boolean`
   - **Category**: `state`
4. Click **Add Configuration**

### 8.3 Test with API Key

Test your API with the new game and API key:
```
https://yourdomain.com/game-api/api/v1/config/my_game_001
```

Add the API key in the request header:
```
X-API-Key: your_generated_api_key
```

You should now see your configuration data in the response.

## 9. SSL Certificate

### 9.1 Enable HTTPS

1. In cPanel, navigate to **Security** → **SSL/TLS Status**
2. Find your domain/subdomain
3. Click **Run AutoSSL** if available
4. Ensure SSL is active for your API domain

### 9.2 Force HTTPS

Add to your `.htaccess`:

```apache
# Force HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## 10. Monitoring and Maintenance

### 10.1 Error Logging

Check PHP error logs in cPanel → **Metrics** → **Errors**.

### 10.2 Database Backups

Set up regular database backups in cPanel → **Databases** → **phpMyAdmin** → **Export** or use cPanel's backup tools.

### 10.3 Log Rotation

Consider adding log rotation to prevent log files from growing too large.

## 11. Troubleshooting

### Common Issues

#### 500 Internal Server Error
- Check PHP error logs
- Verify file permissions
- Ensure PHP version compatibility

#### Database Connection Failed
- Verify database credentials in `config/database.php`
- Check database user permissions
- Ensure database exists

#### API Key Not Working
- Verify API key is correctly copied
- Check game status is 'active'
- Ensure API key is being sent in the correct header

#### Admin Panel Not Loading
- Check session directory permissions
- Verify PHP session settings
- Clear browser cookies and cache

### Debug Mode

For debugging, temporarily enable error display in `config/config.php`:

```php
// Remove these lines in production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

## 12. Performance Optimization

### 12.1 Enable OPcache

In cPanel → **Software** → **MultiPHP INI Editor**, enable OPcache:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

### 12.2 Database Optimization

Consider adding these indexes to your database:

```sql
-- Additional performance indexes
CREATE INDEX idx_configurations_game_key ON configurations(game_id, config_key);
CREATE INDEX idx_configurations_category ON configurations(category);
```

### 12.3 Caching

The system includes basic file caching. For high-traffic sites, consider:
- Redis caching (if available)
- CDN for static assets
- Database query caching

## 13. Security Best Practices

### 13.1 Regular Updates

Keep PHP and cPanel updated to the latest versions.

### 13.2 Password Security

- Use strong admin passwords
- Change default admin password immediately
- Consider two-factor authentication if available

### 13.3 API Key Rotation

Regularly rotate API keys for enhanced security.

### 13.4 Monitor Access

Monitor API access logs for unusual activity.

## 14. Production Checklist

Before going live, ensure:

- [ ] Default admin password changed
- [ ] HTTPS enabled and forced
- [ ] CORS settings restricted to game domains
- [ ] Error logging configured
- [ ] Database backups scheduled
- [ ] Rate limiting configured appropriately
- [ ] File permissions set correctly
- [ ] PHP version 7.4+ confirmed
- [ ] SSL certificate valid
- [ ] API endpoints tested with Unity client
- [ ] Admin panel functionality verified
- [ ] Monitoring and alerting set up

This deployment guide provides comprehensive instructions for setting up the Game Configuration API on cPanel hosting, ensuring a secure, performant, and reliable configuration management system for your Unity games.