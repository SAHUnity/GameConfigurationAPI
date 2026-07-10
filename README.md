# High-Performance Game Configuration API

A production-ready, ultra-fast REST API designed specifically for low-resource shared hosting environments (cPanel/Apache/LiteSpeed). It serves dynamic game configurations to Unity clients in single-digit milliseconds on the cache hit path.

By leveraging a **Read-Through OPcache Strategy** and strictly separating the "Write" (Admin) and "Read" (API) paths, this system bypasses database bottlenecks entirely and serves configurations directly from the server's RAM.

## 🚀 Architectural Features

- **OPcache RAM Delivery:** The admin panel pre-parses JSON and writes raw `.php` array files to disk. The API simply `require`s these files, allowing PHP's OPcache to serve requests directly from memory—vastly outperforming traditional database/JSON-parsing architectures.
- **Zero-Bloat Hot Path:** The `/api/v1/...` endpoint is 100% stateless. No `session_start()`, no framework bootstrapping, and **zero runtime schema/directory checks**. 
- **Atomic Cache Updates:** Cache files are written atomically (write to temp -> `rename`) ensuring that even if 100 players request data at the exact millisecond you click "Save", nobody receives a corrupted file. An `opcache_invalidate()` call follows each write so every Apache/PHP-FPM worker sees the fresh file immediately.
- **Robust Rate Limiting:** A fixed-window token bucket algorithm (default: 60 req/min) uses atomic file-locking (`flock`). It safely prevents abuse without needing Redis, APCu, or touching the database, making it resilient even when handling bursts of traffic from CGNAT (Carrier-Grade NAT) networks.
- **Early-Exit CORS:** Preflight `OPTIONS` requests are intercepted immediately, saving Apache workers from executing unnecessary logic.
- **GZIP Output Compression:** Automatically compresses large JSON payloads to save bandwidth and improve delivery speed to mobile clients.

## 📋 Server Requirements

- **PHP 8.2+** (OPcache extension **required** — without it, the in-RAM delivery claim no longer holds and cache files are parsed from disk on every request)
- **MySQL 8.0** or **MariaDB**
- **Apache** or **LiteSpeed** Web Server (with `mod_rewrite` enabled)

## 🛠️ cPanel Installation Guide

### 1. Upload & Prepare
Upload the contents of this repository to your web root (e.g., `public_html` or a subdomain folder). 
*Note: The system uses a strict `.htaccess` file to shield core directories (`src/`, `var/`, `.env`) from public access.*

### 2. Database Setup
Create a new MySQL Database and Database User in cPanel. Add the user to the database with **All Privileges**.

### 3. Configuration
Rename the `.env.example` file to `.env` and configure your credentials:
```ini
DB_HOST=localhost
DB_NAME=your_cpanel_db_name
DB_USER=your_cpanel_db_user
DB_PASS=your_cpanel_db_password

ADMIN_USER=admin
ADMIN_PASSWORD=your_secure_password
```

### 4. Run the Setup Script
Navigate to the setup script in your browser to initialize the system:
`https://yourdomain.com/setup.php`

**What this script does:**
- Creates the required MySQL tables (`games`, `configurations`, `users`).
- Creates the Admin user based on your `.env` credentials.
- Creates the necessary `var/cache/` and `var/rate_limit/` directories.

### 5. 🚨 CRITICAL SECURITY STEP
Once setup is complete, you **MUST delete `setup.php`** from your server. 

### 6. Verify Permissions
Ensure the web server user can write to `var/cache/` and `var/rate_limit/` (the cache `.php` files and rate-limit `.bucket` files are written there at runtime). On most cPanel servers default `0755` directory permissions work because the PHP process runs as the account owner; on hardened hosts you may need `0775` plus the correct group ownership.

---

## 📡 API Usage (For Unity Clients)

**Endpoint:** `GET /api/v1`  
**Header:** `X-API-KEY: {YOUR_API_KEY}`

### Example Request (cURL):
```bash
curl -H "X-API-KEY: a1b2c3d4e5f67890..." https://yourdomain.com/api/v1
```

### Example Success Response (200 OK):
The response body is the configuration object itself — there is no `data` or `source` wrapper, keys arrive at the top level:
```json
{
  "welcome_message": "Welcome to the game!",
  "max_players": 100,
  "daily_rewards": {
    "gold": 100,
    "gems": 5
  }
}
```
*Note: an empty configuration yields `[]` (an empty JSON array/object equivalent). When no cache file exists yet, the API rebuilds it from the database on the same request.*

### Example Rate Limit Response (429 Too Many Requests):
```json
{
    "error": "Rate Limit Exceeded"
}
```

---

## 🎮 Admin Panel Usage

Access the dashboard at `https://yourdomain.com/admin/` and log in using the credentials defined in your `.env` file.

1. **Create a Game:** Click "+" under Games to generate a new game and an API Key.
2. **Add Configurations:** Add Key/Value pairs. 
   - *Pro-tip:* If you enter valid JSON in the Value box (e.g., `{"speed": 1.5}`), the system parses it **once at save time** and stores the decoded value in the `.php` cache file. Unity then receives it as a native JSON object — not a string — on every subsequent request, with no per-request parsing cost.
3. **Instant Deployment:** Every time you Save, Edit, or Delete a config, the cache is instantly and atomically rebuilt. Unity clients get the new data on their very next request.

---

## ⚙️ Advanced Configuration

### Adjusting Rate Limits
By default, clients are limited to 60 requests per minute per IP. To change this, edit `api/index.php`:
```php
$limit = 60;   // Maximum requests
$period = 60;  // Time window in seconds
```

### Performance Pro-Tip (cPanel)
If your game has high traffic, your Apache `access_log` will grow rapidly because it writes a line of text for every single API request. For maximum performance, ask your host to **disable access logging specifically for the `/api/` directory**.

---

## 🛠️ Included Testing Tools

Located in the `tools/` directory, you will find `stress_test.ps1`. You can run this via PowerShell to verify your rate limiter and server performance:

```powershell
.\tools\stress_test.ps1 -Url "https://yourdomain.com/api/v1" -Key "YOUR_API_KEY" -Count 150
```
*Expected result, when all 150 requests land within the same 60-second window: the first 60 succeed (HTTP 200) and the remaining 90 return `429 RATE LIMIT`. If the run spans more than 60 seconds the fixed window resets and you'll see fresh 200s — that's normal, not a bug.*