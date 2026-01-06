# High-Performance Game Configuration API

A production-ready, high-performance REST API designed for shared hosting environments (cPanel/Apache). It serves game configurations to clients with sub-millisecond response times using a "Read-Through" OPcache strategy.

## 🚀 Features

-   **High Performance**: Uses generated `.php` files for caching, leveraging the server's OPcache to serve requests directly from RAM without database overhead.
-   **Stateless API**: The "Hot Path" (`/api/v1/...`) is completely stateless (no `session_start`).
-   **Atomic Updates**: Cache files are written atomically (write temp -> rename) to prevent race conditions during updates.
-   **Rate Limiting**: File-based token bucket algorithm limits abuse (default: 60/min) without touching the database.
-   **Secure Admin Panel**: Session-protected dashboard to manage games and configurations, including **Edit/Update** functionality.
-   **Zero Bloat**: No heavy frameworks. Uses a custom lightweight PSR-4 autoloader.
-   **Header-Only Auth**: Strictly enforces `X-API-KEY` header for improved security.
-   **Deployment Tools**: Includes PowerShell tools for stress testing and secure credential generation.
-   **Cloudflare Ready**: Optimized for Cloudflare with automatic IP resolution (`CF-Connecting-IP`) and origin bypass protection.

## 📋 Requirements

-   **PHP 8.2+**
-   **MySQL 8.0** or **MariaDB**
-   **Apache** Web Server (with `mod_rewrite` enabled)
-   **Cloudflare** (Recommended for origin protection and performance)

## 🛠️ cPanel Installation Guide

1.  **Upload Files**
    -   Upload the contents of this project to your `public_html` directory (or a subdirectory).
    -   Ensure the structure looks like this:
        ```
        /public_html
          ├── .env             (Create this from .env.example)
          ├── .htaccess
          ├── autoload.php
          ├── setup.php
          ├── index.php
          ├── admin/
          ├── api/
          ├── src/
          └── var/
        ```

2.  **Database Setup**
    -   Create a Database and User via cPanel "MySQL Databases".
    -   Take note of the Database Name, User, and Password.

3.  **Configuration**
    -   Rename `.env.example` to `.env`.
    -   Edit `.env` with your database and admin credentials:
        ```ini
        DB_HOST=localhost
        DB_NAME=your_cpanel_db_name
        DB_USER=your_cpanel_db_user
        DB_PASS=your_cpanel_db_password

        ADMIN_USER=your_admin_username
        ADMIN_PASSWORD=your_secure_password
        ```

4.  **Installation**
    -   Run `setup.php` to initialize the database and creating the admin user.
    -   **Recommended**: Run via command line: `php setup.php`
    -   **Alternative**: If SSH is unavailable, access via browser (e.g., `https://yourdomain.com/setup.php`) then **delete the file immediately**.
    -   The script will automatically create the admin user defined in your `.env`. If you change the password in `.env` and run `setup.php` again, it will update the existing user's password.

5.  **Directory Security**
    -   The `.htaccess` file included in the root directory is critical. It blocks access to sensitive files like `.env`, `src/`, and `autoload.php`. Ensure your web server allows `.htaccess` overrides.
    -   **Origin Protection**: By default, `.htaccess` blocks all requests that do not originate from Cloudflare (missing `CF-Connecting-IP` header). To test locally without Cloudflare, you must temporarily comment out the origin protection rules in `.htaccess`.

### Deployment Note

The project is designed to run from the root.
-   **Admin Panel**: `https://yourdomain.com/admin/`
-   **API Endpoint**: `https://yourdomain.com/api/v1` (Strictly enforced; direct access to `/api/` or `/api/index.php` is blocked).

## 📡 API Usage

**Endpoint**: `GET /api/v1`
**Header**: `X-API-KEY: {YOUR_API_KEY}`

**Example Request**:
```bash
curl -H "X-API-KEY: a1b2c3d4e5f6..." https://yourdomain.com/api/v1
```

**Response (200 OK)**:
```json
{
    "max_players": 100,
    "server_url": "https://play.example.com",
    "maintenance_mode": false
}
```

**Response (429 Too Many Requests)**:
```json
{
    "error": "Rate Limit Exceeded"
}
```

## ⚙️ Configuration

### Rate Limiting
To change the rate limit (default 60 req/min), edit `api/index.php`:
```php
$limit = 60; // requests
$period = 60; // seconds
```

### Cache
Cache files are stored in `var/cache/`. They are automatically regenerated when you save configs in the Admin Panel. 
To manually clear the cache, you can delete the contents of `var/cache`.

## 🛠️ Utility Tools

Located in the `tools/` directory:

### Stress Test (`stress_test.ps1`)
Simulates high traffic to verify performance and rate limiting.
```powershell
.\tools\stress_test.ps1 -Url "http://yourdomain.com/api/v1" -Key "YOUR_KEY" -Count 100
```

## 🛡️ Security Checklist for Production

-   [ ] **Delete `setup.php`** after installation.
-   [ ] Ensure `var/` directory is writable by the web server.
-   [ ] Verify that sensitive files (`.env`) are NOT accessible via browser.
-   [ ] Verify Cloudflare origin protection is active in `.htaccess`.
