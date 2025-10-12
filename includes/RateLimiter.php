<?php

/**
 * Rate Limiting class for Game Configuration API
 */
class RateLimiter
{
    /**
     * Check if IP address has exceeded rate limit
     * @param string $ip IP address
     * @param int $limit Number of requests allowed
     * @param int $window Time window in seconds
     * @return bool True if within limit, false if exceeded
     */
    public static function checkLimit($ip, $limit = RATE_LIMIT_REQUESTS, $window = RATE_LIMIT_WINDOW)
    {
        if (!file_exists(RATE_LIMIT_PATH)) {
            mkdir(RATE_LIMIT_PATH, 0755, true);
        }

        $rateFile = RATE_LIMIT_PATH . '/' . md5($ip) . '.json';
        $now = time();

        // Clean old rate limit files periodically
        if (rand(1, 100) === 1) {
            self::cleanupOldFiles();
        }

        if (file_exists($rateFile)) {
            $data = json_decode(file_get_contents($rateFile), true);

            // Reset if window has passed
            if ($now - $data['reset_time'] > $window) {
                $data = ['count' => 0, 'reset_time' => $now];
            }

            // Check limit
            if ($data['count'] >= $limit) {
                return false;
            }

            $data['count']++;
        } else {
            $data = ['count' => 1, 'reset_time' => $now];
        }

        // Save updated data
        file_put_contents($rateFile, json_encode($data));
        return true;
    }

    /**
     * Get remaining requests for IP
     * @param string $ip IP address
     * @param int $limit Number of requests allowed
     * @param int $window Time window in seconds
     * @return array Remaining requests and reset time
     */
    public static function getRemainingRequests($ip, $limit = RATE_LIMIT_REQUESTS, $window = RATE_LIMIT_WINDOW)
    {
        $rateFile = RATE_LIMIT_PATH . '/' . md5($ip) . '.json';
        $now = time();

        if (!file_exists($rateFile)) {
            return ['remaining' => $limit, 'reset_time' => $now + $window];
        }

        $data = json_decode(file_get_contents($rateFile), true);

        // Reset if window has passed
        if ($now - $data['reset_time'] > $window) {
            return ['remaining' => $limit, 'reset_time' => $now + $window];
        }

        $remaining = max(0, $limit - $data['count']);
        return ['remaining' => $remaining, 'reset_time' => $data['reset_time'] + $window];
    }

    /**
     * Reset rate limit for IP
     * @param string $ip IP address
     */
    public static function resetLimit($ip)
    {
        $rateFile = RATE_LIMIT_PATH . '/' . md5($ip) . '.json';
        if (file_exists($rateFile)) {
            unlink($rateFile);
        }
    }

    /**
     * Clean up old rate limit files
     */
    private static function cleanupOldFiles()
    {
        $files = glob(RATE_LIMIT_PATH . '/*.json');
        $now = time();

        foreach ($files as $file) {
            if ($now - filemtime($file) > RATE_LIMIT_WINDOW * 2) {
                unlink($file);
            }
        }
    }

    /**
     * Get rate limit headers for response
     * @param string $ip IP address
     * @param int $limit Number of requests allowed
     * @param int $window Time window in seconds
     * @return array Rate limit headers
     */
    public static function getRateLimitHeaders($ip, $limit = RATE_LIMIT_REQUESTS, $window = RATE_LIMIT_WINDOW)
    {
        $remaining = self::getRemainingRequests($ip, $limit, $window);

        return [
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => $remaining['remaining'],
            'X-RateLimit-Reset' => $remaining['reset_time']
        ];
    }

    /**
     * Send rate limit headers
     * @param string $ip IP address
     * @param int $limit Number of requests allowed
     * @param int $window Time window in seconds
     */
    public static function sendRateLimitHeaders($ip, $limit = RATE_LIMIT_REQUESTS, $window = RATE_LIMIT_WINDOW)
    {
        $headers = self::getRateLimitHeaders($ip, $limit, $window);

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
    }
}
