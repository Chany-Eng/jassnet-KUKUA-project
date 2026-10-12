<?php

declare(strict_types=1);

namespace Jassnet\Api\Http;

final class Response
{
    public static function json(int $status, array $body, array $headers = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public static function ok(array $data, int $status = 200): never
    {
        self::json($status, ['ok' => true, 'data' => $data]);
    }

    public static function error(int $status, string $code, string $message): never
    {
        self::json($status, ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
    }
}
