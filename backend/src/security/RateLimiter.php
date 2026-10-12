<?php

declare(strict_types=1);

namespace Jassnet\Api\Security;

use PDO;

final class RateLimiter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function allow(string $scope, string $identity, int $limit, int $windowSeconds): bool
    {
        $key = (string) getenv('APP_KEY');
        if (strlen($key) < 32) {
            throw new \RuntimeException('APP_KEY must be configured with at least 32 characters.');
        }
        $hash = hash_hmac('sha256', $identity, $key);
        $this->pdo->beginTransaction();
        try {
            $seed = $this->pdo->prepare('INSERT INTO rate_limits (scope, identity_hash, hit_count, window_started_at) VALUES (?, ?, 0, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE identity_hash = VALUES(identity_hash)');
            $seed->execute([$scope, $hash]);
            $stmt = $this->pdo->prepare('SELECT hit_count, window_started_at FROM rate_limits WHERE scope = ? AND identity_hash = ? FOR UPDATE');
            $stmt->execute([$scope, $hash]);
            $row = $stmt->fetch();
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            if (!$row || $now->getTimestamp() - strtotime($row['window_started_at'] . ' UTC') >= $windowSeconds) {
                $stmt = $this->pdo->prepare('UPDATE rate_limits SET hit_count = 1, window_started_at = UTC_TIMESTAMP() WHERE scope = ? AND identity_hash = ?');
                $stmt->execute([$scope, $hash]);
                $this->pdo->commit();
                return true;
            }
            if ((int) $row['hit_count'] >= $limit) {
                $this->pdo->commit();
                return false;
            }
            $stmt = $this->pdo->prepare('UPDATE rate_limits SET hit_count = hit_count + 1 WHERE scope = ? AND identity_hash = ?');
            $stmt->execute([$scope, $hash]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
