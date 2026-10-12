<?php

declare(strict_types=1);

namespace Jassnet\Api\Security;

final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE_BYTES = 12;
    private const TAG_BYTES = 16;

    public static function encryptQueueValue(string $plaintext): string
    {
        $key = self::deriveKey();
        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG_BYTES);
        if ($ciphertext === false || strlen($tag) !== self::TAG_BYTES) {
            throw new \RuntimeException('Queue payload encryption failed.');
        }
        return base64_encode($nonce . $tag . $ciphertext);
    }

    public static function decryptQueueValue(string $envelope): string
    {
        $packed = base64_decode($envelope, true);
        if ($packed === false || strlen($packed) <= self::NONCE_BYTES + self::TAG_BYTES) {
            throw new \RuntimeException('Queue payload is invalid.');
        }
        $nonce = substr($packed, 0, self::NONCE_BYTES);
        $tag = substr($packed, self::NONCE_BYTES, self::TAG_BYTES);
        $ciphertext = substr($packed, self::NONCE_BYTES + self::TAG_BYTES);
        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, self::deriveKey(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($plaintext === false) {
            throw new \RuntimeException('Queue payload authentication failed.');
        }
        return $plaintext;
    }

    private static function deriveKey(): string
    {
        $masterKey = (string) getenv('APP_KEY');
        if (strlen($masterKey) < 32) {
            throw new \RuntimeException('APP_KEY must be configured with at least 32 characters.');
        }
        if (!function_exists('openssl_encrypt') || !in_array(self::CIPHER, openssl_get_cipher_methods(), true)) {
            throw new \RuntimeException('Authenticated encryption is unavailable.');
        }
        return hash_hkdf('sha256', $masterKey, 32, 'jassnet-otp-queue-v1');
    }
}
