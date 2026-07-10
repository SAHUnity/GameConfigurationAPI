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
    http_response_code(500);
    echo json_encode(['error' => 'Critical Error: Core files not found.']);
    exit;
}

require $rootPath . 'autoload.php';

// Exit early for CORS Preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    Config::load($rootPath . '.env');
} catch (Exception $e) {
    Response::error("Internal Server Error: Config", 500);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitDir = $rootPath . 'var/rate_limit/';
$rateLimitFile = $rateLimitDir . md5($ip) . '.bucket';

$limit = 60; // requests
$period = 60; // seconds

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

$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;

if (!$apiKey) {
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        $apiKey = $headers['X-API-KEY'] ?? null;
    }
}

if (!$apiKey || !preg_match('/^[a-f0-9]{64}$/', $apiKey)) {
    Response::error("Missing or Invalid X-API-KEY header", 400);
}

$cacheFile = $rootPath . 'var/cache/' . $apiKey . '.php';

if (file_exists($cacheFile)) {
    $config = require $cacheFile;
    Response::json($config);
}

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
