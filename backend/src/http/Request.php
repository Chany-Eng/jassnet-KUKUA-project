<?php

declare(strict_types=1);

namespace Jassnet\Api\Http;

final class Request
{
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $headers,
        public readonly string $rawBody,
        public readonly string $ip
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (!is_array($headers)) {
            $headers = [];
        }
        $path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
        $apiPosition = strpos($path, '/api/v1');
        if ($apiPosition !== false) {
            $path = substr($path, $apiPosition);
        }
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            array_change_key_case($headers, CASE_LOWER),
            file_get_contents('php://input') ?: '',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
    }

    public function json(): array
    {
        if ($this->rawBody === '') {
            return [];
        }
        $decoded = json_decode($this->rawBody, true);
        if (!is_array($decoded)) {
            Response::error(400, 'invalid_json', 'Ombi lina muundo usio sahihi.');
        }
        return $decoded;
    }
}
