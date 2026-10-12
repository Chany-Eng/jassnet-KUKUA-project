<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/') ?: '/';
$apiBase = '/api/v1';

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($method === 'GET' && ($path === $apiBase . '/health' || $path === '/health')) {
    $database = 'not_configured';
    $required = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME'];
    $configured = true;
    foreach ($required as $name) {
        if (getenv($name) === false || getenv($name) === '') {
            $configured = false;
            break;
        }
    }

    if ($configured) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                getenv('DB_HOST'),
                getenv('DB_PORT'),
                getenv('DB_DATABASE')
            );
            $pdo = new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD') ?: '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 2,
            ]);
            $pdo->query('SELECT 1');
            $database = 'ok';
        } catch (Throwable $error) {
            // Keep internal connection details out of public health responses.
            $database = 'unavailable';
        }
    }

    $healthy = $database === 'ok';
    respond($healthy ? 200 : 503, [
        'ok' => $healthy,
        'data' => [
            'service' => 'jassnet-billing-api',
            'status' => $healthy ? 'ready' : 'not_configured',
            'database' => $database,
            'integrations' => [
                'radius' => 'unverified',
                'routers' => 'unverified',
                'payments' => 'disabled',
                'sms' => 'disabled',
            ],
        ],
    ]);
}

if (str_starts_with($path, $apiBase)) {
    respond(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Endpoint haipatikani.']]);
}

respond(404, ['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Resource haipatikani.']]);
