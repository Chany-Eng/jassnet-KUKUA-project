<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/src/storage/Database.php';

use Jassnet\Api\Storage\Database;

$email = strtolower(trim($argv[1] ?? ''));
$roleName = trim($argv[2] ?? '');
$allowedRoles = ['Super Admin', 'Admin', 'Technician', 'Accountant', 'Customer Service', 'Agent'];
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || !in_array($roleName, $allowedRoles, true)) {
    fwrite(STDERR, "Usage: php create_staff.php <email> <role name>\nAllowed roles: " . implode(', ', $allowedRoles) . "\nPassword must be piped through STDIN; it must not be passed as an argument.\n");
    exit(2);
}
if (function_exists('stream_isatty') && stream_isatty(STDIN)) {
    fwrite(STDERR, "Password input must be piped securely to STDIN, not typed into a visible terminal prompt.\n");
    exit(2);
}
$password = fgets(STDIN);
if ($password === false) {
    fwrite(STDERR, "No password was received on STDIN.\n");
    exit(2);
}
$password = rtrim($password, "\r\n");
if (strlen($password) < 14 || strlen($password) > 1024) {
    fwrite(STDERR, "Password must be at least 14 characters and no more than 1024 bytes.\n");
    exit(2);
}

try {
    $pdo = Database::connection();
    $pdo->beginTransaction();
    $roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
    $roleStmt->execute([$roleName]);
    $role = $roleStmt->fetch();
    if (!$role) {
        throw new RuntimeException('Role is missing; apply reviewed migrations first.');
    }
    $insert = $pdo->prepare("INSERT INTO users (email, password_hash, status, mfa_enabled) VALUES (?, ?, 'active', 0)");
    $insert->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();
    $assign = $pdo->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)');
    $assign->execute([$userId, $role['id']]);
    $audit = $pdo->prepare("INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, details) VALUES (?, 'staff.bootstrap', 'user', ?, JSON_OBJECT('role', ?))");
    $audit->execute([$userId, (string) $userId, $roleName]);
    $pdo->commit();
    fwrite(STDOUT, "Staff account created. Email: {$email}; role: {$roleName}. Password was not displayed or stored in plaintext.\n");
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Staff account was not created. Check database, migrations, and whether the email is already registered.\n");
    exit(1);
} finally {
    if (isset($password)) {
        sodium_memzero($password);
    }
}
