<?php

use App\CacheService;
use App\Config;
use App\Database;
use App\Models\Game;
use App\Utils\Response;

// 1. Load Autoloader & Config
// Dynamic Path Resolution to handle both "Standard" and "Public Root" deployments
$possiblePaths = [
    __DIR__ . '/../../', // Standard: public/api -> public -> root
    __DIR__ . '/../',    // Flattened: api -> root
];

$rootPath = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path . 'autoload.php')) {
        $rootPath = $path;
        break;
    }
}

if (!$rootPath) {
    Response::error("Critical Error: Core files not found.", 500);
}

require $rootPath . 'autoload.php';

try {
    Config::load($rootPath . '.env');
} catch (Exception $e) {
    Response::error("Internal Server Error: Config", 500);
}

// 2. Rate Limiting (File-based Token Bucket)
// Constraints: Do not use DB. Use var/rate_limit/
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitDir = $rootPath . 'var/rate_limit/';
$rateLimitFile = $rateLimitDir . md5($ip) . '.bucket';

// Rate Limit Config
$limit = 60; // requests
$period = 60; // seconds

if (!is_dir($rateLimitDir)) {
    mkdir($rateLimitDir, 0755, true);
}

// Simple logic:
// Store timestamps of recent requests. 
// Filter out those older than 60s.
// If count > limit, block.
// To avoid infinite file growth/locking issues, we just store "allowance" and "last_check" (Token Bucket)
// or just a list of timestamps (Sliding Window). 
// Token bucket is better for "burst" control but User asked for "60 requests per minute". 
// A simple text file with "timestamp" of the START of the minute + count is easiest for file-locking.

$fp = fopen($rateLimitFile, 'c+');
if ($fp && flock($fp, LOCK_EX)) {
    $stat = fstat($fp);
    $content = $stat['size'] > 0 ? fread($fp, $stat['size']) : '';
    
    $data = $content ? json_decode($content, true) : null;
    $now = time();
    
    if (!$data || ($now - $data['start_time'] > 60)) {
        // Reset bucket
        $data = [
            'start_time' => $now,
            'count' => 1
        ];
    } else {
        // Increment
        $data['count']++;
    }

    if ($data['count'] > $limit) {
        flock($fp, LOCK_UN);
        fclose($fp);
        Response::error("Rate Limit Exceeded", 429);
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data));
    flock($fp, LOCK_UN);
    fclose($fp);
} else {
    // If we can't lock, fail open or closed? Fail open (allow) to avoid DOSing ourselves if FS is slow.
    if ($fp) fclose($fp);
}

// 3. API Key Validation
// header-only as requested
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

if (!$apiKey) {
    // Fallback for some server configs that don't populate HTTP_ prefix automatically
    // or if using getallheaders() (apache only usually)
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $apiKey = $headers['X-API-KEY'] ?? null;
    }
}

// 4. Cache Check (The Hot Path)
// Note: $apiKey validation happens after getting it
if (!$apiKey || !preg_match('/^[a-f0-9]{64}$/', $apiKey)) {
    Response::error("Missing or Invalid X-API-KEY header", 400);
}

$cacheFile = $rootPath . 'var/cache/' . $apiKey . '.php';

if (file_exists($cacheFile)) {
    // HIT: No DB Connection
    $config = require $cacheFile;
    Response::json($config);
}

// 5. Cache Miss (The Cold Path fallback)
// Only now do we touch the Database
try {
    $game = Game::getByApiKey($apiKey);
    
    if (!$game) {
        // Prevent cache stamping for invalid keys? 
        // Maybe rate limit invalid keys strictly?
        Response::error("Invalid API Key", 401);
    }

    // Refresh the cache
    $cacheService = new CacheService();
    $cacheService->refresh($game['id']);

    // Read it back (or we could just have refresh return the data, but strict separation is safer)
    if (file_exists($cacheFile)) {
        $config = require $cacheFile;
        Response::json($config);
    } else {
        // Empty config or failed write, return empty object/array
        Response::json([]);
    }

} catch (Exception $e) {
    // Don't expose DB errors in production
    Response::error("Internal Server Error", 500);
}
