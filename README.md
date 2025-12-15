# High-Performance Game Configuration API

A production-ready, high-performance REST API designed for shared hosting environments (cPanel/Apache). It serves game configurations to clients with sub-millisecond response times using a "Read-Through" OPcache strategy.

## 🚀 Features

-   **High Performance**: Uses generated `.php` files for caching, leveraging the server's OPcache to serve requests directly from RAM without database overhead.
-   **Stateless API**: The "Hot Path" (`/api/v1/...`) is completely stateless (no `session_start`).
-   **Atomic Updates**: Cache files are written atomically (write temp -> rename) to prevent race conditions during updates.
-   **Rate Limiting**: File-based token bucket algorithm limits abuse (default: 60/min) without touching the database.
-   **Secure Admin Panel**: Session-protected dashboard to manage games and configurations.
-   **Zero Bloat**: No heavy frameworks. Uses a custom lightweight PSR-4 autoloader.

## 📋 Requirements

-   **PHP 8.2+**
-   **MySQL 8.0** or **MariaDB**
-   **Apache** Web Server (with `mod_rewrite` enabled)

## 🛠️ cPanel Installation Guide

1.  **Upload Files**
    -   Upload the contents of this project to your `public_html` directory (or a subdirectory).
    -   Ensure the structure looks like this:
        ```
        /public_html
          ├── .env             (Create this from .env.example)
          ├── autoload.php
          ├── setup.php
          ├── public/
          ├── src/
          └── var/
        ```

2.  **Database Setup**
    -   Create a Database and User via cPanel "MySQL Databases".
    -   Take note of the Database Name, User, and Password.

3.  **Configuration**
    -   Rename `.env.example` to `.env`.
    -   Edit `.env` with your database credentials:
        ```ini
        DB_HOST=localhost
        DB_NAME=your_cpanel_db_name
        DB_USER=your_cpanel_db_user
        DB_PASS=your_cpanel_db_password
        ```

4.  **Installation**
    -   Open your browser and verify the path to `setup.php`.
    -   Usually: `https://yourdomain.com/setup.php` OR if you kept the folder structure strictly, you might need to run it via command line or move it temporarily to `public/` if you can't reach the root.
    -   **Recommendation**: The safest way is to run `php setup.php` via the cPanel Terminal or SSH.
    -   If you cannot access SSH, you can temporarily move `setup.php` to `public/setup.php`, run it in the browser, and then **delete it immediately**.

5.  **Admin User**
    -   The setup script creates a default user:
        -   **Username**: `admin`
        -   **Password**: `password`
    -   **IMPORTANT**: Change this password immediately via the database (update `users` table with a new bcrypt hash) or use a temporary reset script.

6.  **Directory Security**
    -   Point your domain's Document Root to the `public/` folder if possible.
    -   If you are on shared hosting and cannot change the Document Root, the provided `.htaccess` in `public/` helps, but you should ensure `src/`, `var/`, and `.env` are protected. ideally, place the project *one level up* from `public_html` and only link the contents of `public/` to `public_html`.

### Deployment Scenarios

**Scenario 1: `mydomain.com/game-api` (Subfolder)**
**Scenario 2: `sub.domain.com` (Subdomain Root)**

For both of these, use the **Flattened Structure** (Option B) for the cleanest URLs:
1.  Create the folder `game-api` (or your subdomain root).
2.  Upload `src/`, `var/`, `autoload.php`, `setup.php`, and `.env` into it.
3.  Upload the **contents** of `public/` (`api`, `admin`, `.htaccess`, `index.php`) into it.
4.  **Result**: You can access files at `mydomain.com/game-api/api/v1` (with Header) and the system works automatically.

**Option A: Standard Structure (Nested)**
Use this if you want to keep the source code "above" the web root for extra security, or if you don't mind the `/public/` in the URL.
*   Path: `mydomain.com/game-api/public/`

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
To change the rate limit (default 60 req/min), edit `public/api/index.php`:
```php
// Lines 24-25
$limit = 60; // requests
$period = 60; // seconds
```

### Cache
Cache files are stored in `var/cache/`. They are automatically regenerated when you save configs in the Admin Panel. 
To manually clear the cache, you can delete the contents of `var/cache`.

## 🛡️ Security Checklist for Production

-   [ ] **Change Default Admin Password**.
-   [ ] **Delete `setup.php`** after running it.
-   [ ] Ensure `var/` directory is writable by the web server (chmod 755 or 775).
-   [ ] Verify that `.env` is NOT accessible via browser (`https://yourdomain.com/.env` should return 403 Forbidden).
