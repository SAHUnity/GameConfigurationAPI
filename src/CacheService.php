<?php

namespace App;

use App\Models\Configuration;
use App\Models\Game;
use PDO;

class CacheService
{
    private string $cacheDir;

    public function __construct()
    {
        $this->cacheDir = __DIR__ . '/../var/cache/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function refresh(int $gameId): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("SELECT api_key FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
        $apiKey = $stmt->fetchColumn();

        if (!$apiKey) {
            return;
        }

        $configs = Configuration::getAllForGame($gameId);
        $data = [];
        foreach ($configs as $cfg) {
            $val = $cfg['value'];
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $val = $decoded;
            }
            $data[$cfg['key_name']] = $val;
        }

        $this->write($apiKey, $data);
    }

    public function write(string $apiKey, array $data): void
    {
        $filePath = $this->cacheDir . $apiKey . '.php';
        $tempPath = $filePath . '.tmp';

        $content = "<?php\n\nreturn " . var_export($data, true) . ";\n";

        if (file_put_contents($tempPath, $content) === false) {
            return;
        }

        rename($tempPath, $filePath);
        
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($filePath, true);
        }
    }

    public function delete(string $apiKey): void
    {
        $filePath = $this->cacheDir . $apiKey . '.php';
        if (file_exists($filePath)) {
            unlink($filePath);
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($filePath, true);
            }
        }
    }
}
