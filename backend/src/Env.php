<?php

declare(strict_types=1);

namespace Jassnet\Api;

final class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name) || getenv($name) !== false) {
                continue;
            }
            if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }

        // XAMPP local development can safely create its own persistent key.
        // Production environments must always provide APP_KEY explicitly.
        if (getenv('APP_ENV') === 'local' && strlen((string) getenv('APP_KEY')) < 32) {
            $key = bin2hex(random_bytes(32));
            $updated = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, (string) file_get_contents($path), 1, $count);
            if ($updated === null || $count !== 1 || file_put_contents($path, $updated, LOCK_EX) === false) {
                throw new \RuntimeException('Could not initialize local APP_KEY.');
            }
            putenv('APP_KEY=' . $key);
            $_ENV['APP_KEY'] = $key;
        }
    }
}
