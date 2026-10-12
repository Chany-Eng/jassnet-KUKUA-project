<?php

declare(strict_types=1);

namespace Jassnet\Api\Storage;

use PDO;

final class Database
{
    public static function configured(): bool
    {
        foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'] as $key) {
            if (!getenv($key)) {
                return false;
            }
        }
        return extension_loaded('pdo_mysql');
    }

    public static function connection(): PDO
    {
        if (!self::configured()) {
            throw new \RuntimeException('Database is not configured.');
        }
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_DATABASE'));
        return new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 3,
        ]);
    }
}
