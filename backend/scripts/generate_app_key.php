<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$envPath = dirname(__DIR__) . '/.env';
if (!is_file($envPath) || !is_writable($envPath)) {
    fwrite(STDERR, "Create backend/.env from .env.example first and ensure it is writable.\n");
    exit(1);
}
$content = file_get_contents($envPath);
if ($content === false) {
    fwrite(STDERR, "Could not read backend/.env.\n");
    exit(1);
}
if (preg_match('/^APP_KEY=(.+)$/m', $content, $matches) && trim($matches[1]) !== '') {
    fwrite(STDERR, "APP_KEY is already set; refusing to replace it.\n");
    exit(1);
}
if (!preg_match('/^APP_KEY=.*$/m', $content)) {
    fwrite(STDERR, "APP_KEY entry is missing from backend/.env.\n");
    exit(1);
}
$key = bin2hex(random_bytes(32));
$updated = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $content, 1, $count);
if ($updated === null || $count !== 1 || file_put_contents($envPath, $updated, LOCK_EX) === false) {
    fwrite(STDERR, "Could not securely save APP_KEY.\n");
    exit(1);
}
fwrite(STDOUT, "A cryptographically random APP_KEY was generated and saved locally. Keep backend/.env private and back it up securely.\n");
