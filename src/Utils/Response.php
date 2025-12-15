<?php

namespace App\Utils;

class Response
{
    public static function json($data, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($headers as $key => $value) {
            header("$key: $value");
        }

        // Output compression if possible and not already handled by Apache
        if (!in_array('ob_gzhandler', ob_list_handlers())) {
            if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
               ob_start('ob_gzhandler');
            }
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit; // Ensure execution stops
    }
    
    public static function error(string $message, int $status = 400): void
    {
        self::json(['error' => $message], $status);
    }
}
