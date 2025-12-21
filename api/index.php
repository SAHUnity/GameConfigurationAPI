<?php

use App\CacheService;
use App\Config;
use App\Database;
use App\Models\Game;
use App\Utils\Response;

// 1. Load Autoloader & Config
$possiblePaths = [
    __DIR__ . '/../',
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

// 2. Rate Limiting (Token Bucket)
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitDir = $rootPath . 'var/rate_limit/';
$rateLimitFile = $rateLimitDir . md5($ip) . '.bucket';

$limit = 60; // requests
$period = 60; // seconds

if (!is_dir($rateLimitDir)) {
    mkdir($rateLimitDir, 0755, true);
}

$fp = fopen($rateLimitFile, 'c+');
if ($fp && flock($fp, LOCK_EX)) {
    $stat = fstat($fp);
    $content = $stat['size'] > 0 ? fread($fp, $stat['size']) : '';
    
    $data = $content ? json_decode($content, true) : null;
    $now = time();
    
    if (!$data || ($now - $data['start_time'] > 60)) {
        $data = [
            'start_time' => $now,
            'count' => 1
        ];
    } else {
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
    if ($fp) fclose($fp);
}

// 3. API Key Validation
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

if (!$apiKey) {
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $apiKey = $headers['X-API-KEY'] ?? null;
    }
}

// 4. Cache Check (Hot Path)
if (!$apiKey || !preg_match('/^[a-f0-9]{64}$/', $apiKey)) {
    Response::error("Missing or Invalid X-API-KEY header", 400);
}

$cacheFile = $rootPath . 'var/cache/' . $apiKey . '.php';

if (file_exists($cacheFile)) {
    $config = require $cacheFile;
    Response::json($config);
}

// 5. Cache Miss (Cold Path)
try {
    $game = Game::getByApiKey($apiKey);
    
    if (!$game) {
        Response::error("Invalid API Key", 401);
    }

    $cacheService = new CacheService();
    $cacheService->refresh($game['id']);

    if (file_exists($cacheFile)) {
        $config = require $cacheFile;
        Response::json($config);
    } else {
        Response::json([]);
    }

} catch (Exception $e) {
    Response::error("Internal Server Error", 500);
}
