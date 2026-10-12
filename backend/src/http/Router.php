<?php

declare(strict_types=1);

namespace Jassnet\Api\Http;

final class Router
{
    /** @var array<string, array{0: string, 1: callable}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method) . ' ' . $path] = [$path, $handler];
    }

    public function dispatch(Request $request): void
    {
        $key = $request->method . ' ' . $request->path;
        if (!isset($this->routes[$key])) {
            Response::error(404, 'not_found', 'Endpoint haipatikani.');
        }
        ($this->routes[$key][1])($request);
    }
}
