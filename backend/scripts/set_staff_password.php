<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/src/storage/Database.php';
require_once dirname(__DIR__) . '/src/Env.php';
\Jassnet\Api\Env::load(dirname(__DIR__) . '/.env');

$username = strtolower(trim($argv[1] ?? ''));
if (!preg_match('/^[a-z][a-z0-9_-]{0,39}\.[a-z][a-z0-9_-]{0,39}$/', $username)) {
    fwrite(STDERR, "Usage: php set_staff_password.php <firstname.lastname>\nPassword must be piped through STDIN, not passed as an argument.\n");
    exit(2);
}
if (function_exists('stream_isatty') && stream_isatty(STDIN)) {
    fwrite(STDERR, "Password must be entered through the hidden PowerShell prompt.\n");
    exit(2);
}
$password = fgets(STDIN);
if ($password === false) {
    fwrite(STDERR, "No password was received on STDIN.\n");
    exit(2);
}
$password = rtrim($password, "\r\n");
if (strlen($password) < 14 || strlen($password) > 1024) {
    fwrite(STDERR, "Choose a unique password with at least 14 characters.\n");
    exit(2);
}

try {
    $pdo = \Jassnet\Api\Storage\Database::connection();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id = u.id JOIN roles r ON r.id = ur.role_id WHERE u.username = ? AND r.name = 'Super Admin' AND u.status IN ('locked', 'active') LIMIT 1 FOR UPDATE");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) {
        throw new RuntimeException('Active or locked Super Admin account not found.');
    }
    $update = $pdo->prepare("UPDATE users SET password_hash = ?, status = 'active' WHERE id = ?");
    $update->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    $audit = $pdo->prepare("INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, details) VALUES (?, 'staff.password_set_by_local_operator', 'user', ?, JSON_OBJECT('username', ?))");
    $audit->execute([$user['id'], (string) $user['id'], $username]);
    $pdo->commit();
    fwrite(STDOUT, "Password set securely for {$username}. The password was not displayed or stored in plaintext.\n");
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Password was not set. Check that the Super Admin account exists and migrations have been applied.\n");
    exit(1);
} finally {
    if (isset($password) && function_exists('sodium_memzero')) {
        sodium_memzero($password);
    }
}
