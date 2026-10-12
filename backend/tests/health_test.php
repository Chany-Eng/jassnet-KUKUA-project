<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/security/Crypto.php';
putenv('APP_KEY=health-test-key-material-with-more-than-32-bytes');

$encrypted = Jassnet\Api\Security\Crypto::encryptQueueValue('otp-test-value');
if (Jassnet\Api\Security\Crypto::decryptQueueValue($encrypted) !== 'otp-test-value') {
    fwrite(STDERR, "Queue encryption round-trip failed.\n");
    exit(1);
}
$packed = base64_decode($encrypted, true);
$packed[strlen($packed) - 1] = chr(ord($packed[strlen($packed) - 1]) ^ 1);
try {
    Jassnet\Api\Security\Crypto::decryptQueueValue(base64_encode($packed));
    fwrite(STDERR, "Queue encryption accepted a tampered payload.\n");
    exit(1);
} catch (RuntimeException) {
    // Authentication correctly rejects modified ciphertext.
}

$socket = stream_socket_server('tcp://127.0.0.1:0', $socketError, $socketMessage);
if (!is_resource($socket)) {
    fwrite(STDERR, "Could not reserve a local test port: {$socketMessage}\n");
    exit(1);
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$port = (int) substr(strrchr($address, ':'), 1);
$base = 'http://127.0.0.1:' . $port . '/api/v1';
$public = realpath(__DIR__ . '/../public');
$command = sprintf('%s -S 127.0.0.1:%d -t %s %s', escapeshellarg(PHP_BINARY), $port, escapeshellarg($public), escapeshellarg($public . '/router.php'));
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($process)) {
    fwrite(STDERR, "Could not start the PHP development server.\n");
    exit(1);
}
fclose($pipes[0]);

try {
    $request = static function (string $path, string $method = 'GET', ?string $body = null) use ($base): array {
        $options = ['method' => $method, 'ignore_errors' => true, 'timeout' => 3, 'header' => "Accept: application/json\r\n"];
        if ($body !== null) {
            $options['header'] .= "Content-Type: application/json\r\n";
            $options['content'] = $body;
        }
        $response = file_get_contents($base . $path, false, stream_context_create(['http' => $options]));
        if ($response === false) {
            throw new RuntimeException('API endpoint did not respond.');
        }
        preg_match('/\s(\d{3})\s/', $http_response_header[0] ?? '', $matches);
        return [(int) ($matches[1] ?? 0), json_decode($response, true, 512, JSON_THROW_ON_ERROR)];
    };

    $health = null;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        try {
            $health = $request('/health');
            break;
        } catch (Throwable) {
            usleep(10000);
        }
    }
    if ($health === null || !in_array($health[0], [200, 503], true)
        || !isset($health[1]['ok'], $health[1]['data']['service'])
        || $health[1]['data']['service'] !== 'jassnet-billing-api') {
        throw new RuntimeException('Health endpoint contract did not match.');
    }

    [$staffInputStatus, $staffInput] = $request('/staff/login', 'POST', '{"email":"not-an-email","password":"x"}');
    if ($staffInputStatus !== 400 || ($staffInput['error']['code'] ?? '') !== 'invalid_request') {
        throw new RuntimeException('Staff login validation did not return the expected JSON error.');
    }
    [$staffSessionStatus, $staffSession] = $request('/staff/me');
    if ($staffSessionStatus !== 401 || ($staffSession['error']['code'] ?? '') !== 'unauthenticated') {
        throw new RuntimeException('Staff session route did not reject an unauthenticated request.');
    }

    [$invalidStatus, $invalid] = $request('/portal/otp/request', 'POST', '{"account_number":"bad"}');
    if ($invalidStatus !== 400 || ($invalid['error']['code'] ?? '') !== 'invalid_request') {
        throw new RuntimeException('Invalid OTP request validation did not return the expected generic JSON error.');
    }

    [$missingStatus, $missing] = $request('/not-a-route');
    if ($missingStatus !== 404 || ($missing['error']['code'] ?? '') !== 'not_found') {
        throw new RuntimeException('Unknown route did not return the expected JSON 404.');
    }
    echo "Queue encryption, health, staff auth guards, request validation, and not-found contracts: PASS\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
} finally {
    proc_terminate($process);
    foreach ([1, 2] as $index) {
        if (isset($pipes[$index]) && is_resource($pipes[$index])) {
            fclose($pipes[$index]);
        }
    }
    proc_close($process);
}
