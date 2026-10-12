<?php

declare(strict_types=1);

namespace Jassnet\Api\Services;

use Jassnet\Api\Http\Response;
use Jassnet\Api\Security\Crypto;
use Jassnet\Api\Security\RateLimiter;
use PDO;

final class OtpService
{
    public function __construct(private readonly PDO $pdo, private readonly RateLimiter $limiter)
    {
    }

    public function request(string $accountNumber, string $ip): never
    {
        $generic = ['accepted' => true, 'message' => 'Ikiwa akaunti inastahili, OTP itatumwa kwenye simu iliyosajiliwa.'];
        if (!$this->limiter->allow('otp_request_ip', $ip, 10, 900)
            || !$this->limiter->allow('otp_request_account', $accountNumber, 3, 900)) {
            Response::ok($generic, 202);
        }

        $stmt = $this->pdo->prepare("SELECT a.id, c.phone FROM accounts a JOIN customers c ON c.id = a.customer_id WHERE a.account_number = ? AND a.service_type = 'pppoe' AND a.status = 'active' AND c.status = 'active' LIMIT 1");
        $stmt->execute([$accountNumber]);
        $account = $stmt->fetch();
        if (!$account) {
            Response::ok($generic, 202);
        }

        $this->pdo->beginTransaction();
        try {
            $accountLock = $this->pdo->prepare("SELECT id FROM accounts WHERE id = ? AND service_type = 'pppoe' AND status = 'active' FOR UPDATE");
            $accountLock->execute([$account['id']]);
            if (!$accountLock->fetch()) {
                $this->pdo->commit();
                Response::ok($generic, 202);
            }
            $lock = $this->pdo->prepare('SELECT id FROM otp_challenges WHERE account_id = ? AND consumed_at IS NULL AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1 FOR UPDATE');
            $lock->execute([$account['id']]);
            if ($lock->fetch()) {
                $this->pdo->commit();
                Response::ok($generic, 202);
            }

            $otp = (string) random_int(100000, 999999);
            $hash = password_hash($otp, PASSWORD_DEFAULT);
            $this->pdo->prepare('UPDATE otp_challenges SET consumed_at = UTC_TIMESTAMP() WHERE account_id = ? AND consumed_at IS NULL')->execute([$account['id']]);
            $insert = $this->pdo->prepare('INSERT INTO otp_challenges (account_id, code_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 MINUTE))');
            $insert->execute([$account['id'], $hash]);
            $challengeId = (int) $this->pdo->lastInsertId();

            // No provider is assumed. Deliver through a configured queue adapter only.
            $maskedPhone = '***' . substr($account['phone'], -3);
            $notification = $this->pdo->prepare("INSERT INTO notifications (customer_id, channel, template_key, destination_masked, status, available_at) SELECT customer_id, 'sms', 'portal_otp', ?, 'queued', UTC_TIMESTAMP() FROM accounts WHERE id = ?");
            $notification->execute([$maskedPhone, $account['id']]);
            $payload = json_encode([
                'challenge_id' => $challengeId,
                'phone' => $account['phone'],
                'encryption' => 'aes-256-gcm-v1',
                'encrypted_otp' => Crypto::encryptQueueValue($otp),
            ], JSON_THROW_ON_ERROR);
            $job = $this->pdo->prepare("INSERT INTO background_jobs (queue_name, job_type, payload, status, available_at) VALUES ('notifications', 'send_otp', ?, 'queued', UTC_TIMESTAMP())");
            $job->execute([$payload]);
            $this->pdo->commit();
            if (getenv('SMS_PROVIDER') !== 'disabled' && getenv('SMS_PROVIDER') !== false) {
                // An approved worker may deliver this queued message after provider verification.
                // No provider-specific request is performed from the API process.
            }
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }

        Response::ok($generic, 202);
    }

    public function verify(string $accountNumber, string $otp, string $ip): never
    {
        if (!$this->limiter->allow('otp_verify_ip', $ip, 30, 900)
            || !$this->limiter->allow('otp_verify_account', $accountNumber, 10, 900)) {
            Response::error(429, 'otp_invalid', 'Namba ya akaunti au OTP si sahihi au muda wake umeisha.');
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT o.id, o.account_id, o.code_hash, o.attempts FROM otp_challenges o JOIN accounts a ON a.id = o.account_id WHERE a.account_number = ? AND a.service_type = 'pppoe' AND o.consumed_at IS NULL AND o.expires_at > UTC_TIMESTAMP() ORDER BY o.id DESC LIMIT 1 FOR UPDATE");
            $stmt->execute([$accountNumber]);
            $challenge = $stmt->fetch();
            if (!$challenge || (int) $challenge['attempts'] >= 5 || !password_verify($otp, $challenge['code_hash'])) {
                if ($challenge) {
                    $update = $this->pdo->prepare('UPDATE otp_challenges SET attempts = attempts + 1 WHERE id = ?');
                    $update->execute([$challenge['id']]);
                }
                $this->pdo->commit();
                Response::error(401, 'otp_invalid', 'Namba ya akaunti au OTP si sahihi au muda wake umeisha.');
            }
            $consume = $this->pdo->prepare('UPDATE otp_challenges SET consumed_at = UTC_TIMESTAMP() WHERE id = ? AND consumed_at IS NULL');
            $consume->execute([$challenge['id']]);
            if ($consume->rowCount() !== 1) {
                $this->pdo->commit();
                Response::error(401, 'otp_invalid', 'Namba ya akaunti au OTP si sahihi au muda wake umeisha.');
            }
            $this->pdo->commit();

            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            session_regenerate_id(true);
            $_SESSION = ['customer_account_id' => (int) $challenge['account_id'], 'authenticated_at' => time()];
            Response::ok(['authenticated' => true]);
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
