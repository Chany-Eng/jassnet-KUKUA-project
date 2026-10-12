<?php

declare(strict_types=1);

namespace Jassnet\Api\Http;

use Jassnet\Api\Security\RateLimiter;
use Jassnet\Api\Services\OtpService;
use Jassnet\Api\Storage\Database;
use PDO;
use Throwable;

final class App
{
    public function __construct(private readonly Request $request)
    {
    }

    public function run(): void
    {
        try {
            $this->configureSession();
            $router = new Router();
            $router->add('GET', '/api/v1/health', fn () => $this->health());
            $router->add('POST', '/api/v1/staff/login', fn (Request $request) => $this->staffLogin($request));
            $router->add('GET', '/api/v1/staff/me', fn () => $this->staffMe());
            $router->add('POST', '/api/v1/staff/logout', fn () => $this->staffLogout());
            $router->add('POST', '/api/v1/portal/otp/request', fn (Request $request) => $this->requestOtp($request));
            $router->add('POST', '/api/v1/portal/otp/verify', fn (Request $request) => $this->verifyOtp($request));
            $router->add('GET', '/api/v1/portal/me', fn () => $this->portalMe());
            $router->add('POST', '/api/v1/portal/logout', fn () => $this->logout());
            $router->dispatch($this->request);
        } catch (Throwable $error) {
            error_log(sprintf('JASSNET API internal failure (%s).', get_class($error)));
            Response::error(500, 'internal_error', 'Hitilafu imetokea. Jaribu tena baadaye.');
        }
    }

    private function configureSession(): void
    {
        $origin = $this->request->headers['origin'] ?? '';
        $allowed = array_filter(array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: '')));
        if ($this->request->method === 'OPTIONS') {
            if ($origin === '' || !in_array($origin, $allowed, true)) {
                Response::error(403, 'origin_forbidden', 'Chanzo cha ombi hakiruhusiwi.');
            }
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
            header('Access-Control-Max-Age: 600');
            header('Vary: Origin');
            http_response_code(204);
            exit;
        }
        if ($origin !== '') {
            if (!in_array($origin, $allowed, true)) {
                Response::error(403, 'origin_forbidden', 'Chanzo cha ombi hakiruhusiwi.');
            }
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '1800');
        session_name('jassnet_session');
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $secureCookie = filter_var(getenv('SESSION_SECURE_COOKIE') ?: (string) $https, FILTER_VALIDATE_BOOLEAN);
        $sameSite = getenv('SESSION_SAME_SITE') ?: 'Strict';
        if (!in_array($sameSite, ['Strict', 'Lax', 'None'], true) || ($sameSite === 'None' && !$secureCookie)) {
            $sameSite = 'Strict';
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secureCookie,
            'httponly' => true,
            'samesite' => $sameSite,
        ]);
    }

    private function health(): never
    {
        $dbStatus = 'not_configured';
        if (Database::configured()) {
            try {
                Database::connection()->query('SELECT 1');
                $dbStatus = 'ok';
            } catch (Throwable) {
                $dbStatus = 'unavailable';
            }
        }
        $healthy = $dbStatus === 'ok';
        Response::json($healthy ? 200 : 503, [
            'ok' => $healthy,
            'data' => [
                'service' => 'jassnet-billing-api',
                'status' => $healthy ? 'ready' : 'not_configured',
                'database' => $dbStatus,
                'integrations' => ['radius' => 'unverified', 'routers' => 'unverified', 'payments' => 'disabled', 'sms' => 'disabled'],
            ],
        ]);
    }

    private function requestOtp(Request $request): never
    {
        $body = $request->json();
        $account = trim((string) ($body['account_number'] ?? ''));
        if (!preg_match('/^[0-9]{4,32}$/', $account)) {
            Response::error(400, 'invalid_request', 'Weka namba halali ya akaunti.');
        }
        $pdo = Database::connection();
        $this->otpService($pdo)->request($account, $request->ip);
    }

    private function staffLogin(Request $request): never
    {
        $body = $request->json();
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $password === '' || strlen($password) > 1024) {
            Response::error(400, 'invalid_request', 'Weka email na password sahihi.');
        }

        $pdo = Database::connection();
        $limiter = new RateLimiter($pdo);
        if (!$limiter->allow('staff_login_ip', $request->ip, 15, 900)
            || !$limiter->allow('staff_login_email', $email, 5, 900)) {
            Response::error(429, 'login_unavailable', 'Huwezi kuingia kwa sasa. Jaribu tena baadaye.');
        }

        $stmt = $pdo->prepare("SELECT u.id, u.password_hash, u.status, u.mfa_enabled FROM users u WHERE u.email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        static $dummyHash = null;
        $hashToVerify = $user['password_hash'] ?? ($dummyHash ??= password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT));
        $validPassword = password_verify($password, $hashToVerify);
        if (!$validPassword || $user['status'] !== 'active' || (bool) $user['mfa_enabled']) {
            Response::error(401, 'invalid_credentials', 'Email au password si sahihi, au akaunti haiwezi kuingia.');
        }

        $rolesStmt = $pdo->prepare("SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.name <> 'Customer' ORDER BY r.name");
        $rolesStmt->execute([$user['id']]);
        $roles = array_column($rolesStmt->fetchAll(), 'name');
        if (!$roles) {
            Response::error(401, 'invalid_credentials', 'Email au password si sahihi, au akaunti haiwezi kuingia.');
        }
        $pdo->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = ?')->execute([$user['id']]);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION = [
            'staff_user_id' => (int) $user['id'],
            'authenticated_at' => time(),
        ];
        Response::ok(['authenticated' => true]);
    }

    private function staffMe(): never
    {
        $userId = $this->requireStaffSession();
        $pdo = Database::connection();
        $userStmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND status = 'active' AND mfa_enabled = 0");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        if (!$user) {
            $this->logoutSession();
            Response::error(401, 'unauthenticated', 'Tafadhali ingia tena.');
        }
        $rolesStmt = $pdo->prepare("SELECT r.name FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.name <> 'Customer' ORDER BY r.name");
        $rolesStmt->execute([$userId]);
        $roles = array_column($rolesStmt->fetchAll(), 'name');
        if (!$roles) {
            $this->logoutSession();
            Response::error(403, 'forbidden', 'Akaunti hii haina ruhusa ya staff.');
        }
        $permissionStmt = $pdo->prepare('SELECT DISTINCT p.name FROM user_roles ur JOIN role_permissions rp ON rp.role_id = ur.role_id JOIN permissions p ON p.id = rp.permission_id WHERE ur.user_id = ? ORDER BY p.name');
        $permissionStmt->execute([$userId]);
        $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
        Response::ok(['email' => $user['email'], 'roles' => $roles, 'permissions' => array_column($permissionStmt->fetchAll(), 'name'), 'csrf_token' => $csrf]);
    }

    private function staffLogout(): never
    {
        $this->requireStaffSession();
        $this->requireCsrf();
        $this->logoutSession();
        Response::ok(['logged_out' => true]);
    }

    private function verifyOtp(Request $request): never
    {
        $body = $request->json();
        $account = trim((string) ($body['account_number'] ?? ''));
        $otp = trim((string) ($body['otp'] ?? ''));
        if (!preg_match('/^[0-9]{4,32}$/', $account) || !preg_match('/^[0-9]{6}$/', $otp)) {
            Response::error(401, 'otp_invalid', 'Namba ya akaunti au OTP si sahihi au muda wake umeisha.');
        }
        $this->otpService(Database::connection())->verify($account, $otp, $request->ip);
    }

    private function portalMe(): never
    {
        $this->requireCustomerSession();
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT a.account_number, a.status AS service_status, a.service_type, p.name AS plan, p.speed_down_kbps, p.speed_up_kbps, s.starts_at, s.ends_at, s.payment_status, s.provisioning_status FROM accounts a JOIN service_plans p ON p.id = a.plan_id LEFT JOIN subscriptions s ON s.account_id = a.id WHERE a.id = ? AND a.service_type = 'pppoe' ORDER BY s.id DESC LIMIT 1");
        $stmt->execute([$_SESSION['customer_account_id']]);
        $account = $stmt->fetch();
        if (!$account) {
            $this->logoutSession();
            Response::error(401, 'unauthenticated', 'Tafadhali ingia tena.');
        }
        $payments = $pdo->prepare("SELECT p.merchant_reference, p.amount_minor, p.currency, p.payment_status, p.created_at FROM payments p JOIN subscriptions s ON s.id = p.subscription_id WHERE s.account_id = ? AND p.payment_status IN ('paid', 'pending', 'failed', 'refunded') ORDER BY p.created_at DESC LIMIT 50");
        $payments->execute([$_SESSION['customer_account_id']]);
        $receipts = $pdo->prepare('SELECT r.receipt_number, r.issued_at, p.amount_minor, p.currency, p.merchant_reference FROM receipts r JOIN payments p ON p.id = r.payment_id JOIN subscriptions s ON s.id = p.subscription_id WHERE s.account_id = ? AND r.voided_at IS NULL ORDER BY r.issued_at DESC LIMIT 50');
        $receipts->execute([$_SESSION['customer_account_id']]);
        $tickets = $pdo->prepare('SELECT id, subject, priority, status, created_at, updated_at FROM tickets WHERE account_id = ? ORDER BY updated_at DESC LIMIT 50');
        $tickets->execute([$_SESSION['customer_account_id']]);
        $csrf = $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
        Response::ok(['account' => $account, 'payments' => $payments->fetchAll(), 'receipts' => $receipts->fetchAll(), 'tickets' => $tickets->fetchAll(), 'csrf_token' => $csrf]);
    }

    private function logout(): never
    {
        $this->requireCustomerSession();
        $this->requireCsrf();
        $this->logoutSession();
        Response::ok(['logged_out' => true]);
    }

    private function requireCustomerSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $authenticatedAt = (int) ($_SESSION['authenticated_at'] ?? 0);
        if (empty($_SESSION['customer_account_id']) || $authenticatedAt < time() - 1800) {
            $this->logoutSession();
            Response::error(401, 'unauthenticated', 'Tafadhali ingia tena.');
        }
    }

    private function requireStaffSession(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $authenticatedAt = (int) ($_SESSION['authenticated_at'] ?? 0);
        $userId = (int) ($_SESSION['staff_user_id'] ?? 0);
        if ($userId < 1 || $authenticatedAt < time() - 1800) {
            $this->logoutSession();
            Response::error(401, 'unauthenticated', 'Tafadhali ingia tena.');
        }
        return $userId;
    }

    private function requireCsrf(): void
    {
        $provided = (string) ($this->request->headers['x-csrf-token'] ?? '');
        $expected = (string) ($_SESSION['csrf_token'] ?? '');
        if ($provided === '' || $expected === '' || !hash_equals($expected, $provided)) {
            Response::error(403, 'csrf_failed', 'Ombi halijathibitishwa. Pakia upya ukurasa ujaribu tena.');
        }
    }

    private function logoutSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => $params['httponly'], 'samesite' => $params['samesite']]);
        }
        session_destroy();
    }

    private function otpService(PDO $pdo): OtpService
    {
        return new OtpService($pdo, new RateLimiter($pdo));
    }
}
