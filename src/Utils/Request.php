<?php

namespace App\Utils;

class Request
{
    public static function getClientIp(): string
    {
        // Cloudflare's trusted header (most reliable when origin protection is active)
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // Fallback for non-CF environments (local dev, direct access)
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function isFromCloudflare(): bool
    {
        return !empty($_SERVER['HTTP_CF_CONNECTING_IP']);
    }
}
