Task: Build High-Performance Game Configuration API (PHP/cPanel)
1. Project Overview

Create a production-ready, high-performance REST API for serving game configurations to Unity clients. The system must run efficiently on low-resource shared hosting (cPanel/Apache).

Primary Objective: Eliminate database bottlenecks by implementing a "Read-Through" caching strategy using PHP OPcache.
Architecture: Strict separation between the "Read API" (Hot Path) and "Write Admin" (Cold Path).

2. Technical Stack

Language: PHP 8.2+ (Strict Types enabled).

Database: MySQL 8.0 / MariaDB.

Frontend: Bootstrap 5 (Admin Dashboard only).

Dependency Management: Composer (Strictly for PSR-4 Autoloading only. Do not use heavy frameworks like Laravel/Symfony).

Server: Apache (Standard cPanel environment).

3. Directory Structure

The agent must generate the files within this specific structure to ensure security and organization:

code
Text
download
content_copy
expand_less
/
├── .env.example           # Environment variable template
├── composer.json          # PSR-4 Autoload definition
├── setup.php              # One-time database migration script
├── public/                # Web Root (Document Root)
│   ├── .htaccess          # Routing & Security rules
│   ├── api/
│   │   └── index.php      # The "Hot Path" endpoint
│   ├── admin/
│   │   ├── index.php      # Dashboard entry
│   │   ├── login.php
│   │   └── assets/        # CSS/JS
│   └── index.php          # Redirects to admin
├── src/                   # Core Logic
│   ├── Config.php         # Env loader
│   ├── Database.php       # Singleton PDO Wrapper
│   ├── CacheService.php   # The PHP-Array caching engine
│   ├── Auth.php           # Admin authentication
│   ├── Models/
│   │   ├── Game.php
│   │   ├── Configuration.php
│   │   └── User.php
│   └── Utils/
│       └── Response.php   # JSON helper
└── var/
    ├── cache/             # Storage for generated .php cache files
    └── logs/              # Error logs
4. Implementation Steps
Phase 1: Foundation & Database

Environment: Create a lightweight .env parser in src/Config.php. Do not use external libraries for this if possible to keep it lean.

Database Connection: Create src/Database.php as a Singleton class.

Use PDO with ATTR_ERRMODE_EXCEPTION.

Disable emulated prepares.

Schema Setup (setup.php):

Create a standalone script to initialize tables. Do not run this logic inside the API.

Table games: id (PK), name, api_key (Unique Index, Varchar 64), is_active.

Table configurations: id, game_id (FK), key_name (Varchar), value (JSON or TEXT), description, is_active. Index on (game_id, key_name) is mandatory.

Table users: id, username, password_hash.

Phase 2: The "Hot Path" (API)

This is the most critical phase for performance. The API must respond in <50ms.

Cache Strategy (src/CacheService.php):

Write: Generate a .php file at var/cache/{api_key}.php.

Atomic Writes: Write to a temp file first, then rename() to the final path to prevent race conditions.

Content: The file must contain: <?php return [ ... array data ... ];.

Read: Use include or require to load the file. This utilizes the server's OPcache (RAM) instead of parsing JSON from disk.

API Endpoint (public/api/index.php):

Step 1: Validate api_key format from GET/Header.

Step 2: Check if var/cache/{api_key}.php exists.

Step 3 (Hit): require the file, output JSON, and exit. (Target: <10ms execution).

Step 4 (Miss): Connect to DB, fetch all active configs for the game, build the array, save to var/cache/{api_key}.php, output JSON.

Constraint: Do NOT start a session (session_start()) in this file. It must be stateless.

Constraint: Use ob_gzhandler if available for output compression.

Phase 3: The "Cold Path" (Admin Dashboard)

Authentication:

Implement standard session-based login in src/Auth.php.

Protect /admin routes.

Game Management:

List/Create/Delete games.

Regenerate API Key: Must delete the old cache file immediately.

Configuration Management:

CRUD for Key/Value pairs.

Save Action: On every save/update/delete, call CacheService::refresh($gameId) to rebuild the .php cache file immediately.

UI: Use simple Bootstrap 5 tables.

Phase 4: Security & Deployment

Rate Limiting:

Implement a token-bucket algorithm using files in var/rate_limit/ (do not use Database for rate limiting).

Limit: 60 requests per minute per IP.

Input Sanitization:

Sanitize all Inputs in Admin.

Output encoding (htmlspecialchars) in Admin HTML.

Apache Config (public/.htaccess):

Route api/v1/{key} to api/index.php.

Block access to .env, composer.json, and the ../src directory.

Add CORS headers (Access-Control-Allow-Origin).

5. Crucial Constraints (Do Not Violate)

NO Runtime Schema Checks: Never check if a table exists during an API request. Assume the DB is ready.

NO JSON Files for Cache: Use .php files returning arrays. This is significantly faster in PHP due to OPcache.

NO Heavy Logs: Do not log every successful API hit to a text file or database. Only log errors.

Vendor Minimalist: Use Composer only for autoloading. Do not require complex packages that bloat the vendor folder.

Atomic Caching: Ensure cache writes are atomic (write temp -> rename) to avoid serving corrupted files during high traffic updates.

6. Definition of Done

Running php setup.php creates the database.

The Admin panel allows creating a game and adding configs.

Requesting the API the first time queries the DB (Debug: "Cache Miss").

Requesting the API the second time does not query the DB (Debug: "Cache Hit").

The system handles concurrent requests efficiently without locking sessions on the API endpoint.