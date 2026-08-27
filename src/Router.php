<?php

declare(strict_types=1);

namespace App;

use App\Controllers\ErrorController;

/**
 * Roteador manual (sem framework). Mapeia método + caminho exato para um
 * handler [Classe::class, 'metodo'] ou um callable.
 * Ver docs/technical/arquitetura.md §10.
 */
final class Router
{
    /** @var array<int, array{method: string, path: string, handler: callable|array}> */
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'path'    => $this->normalize($path),
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = $this->normalize(parse_url($uri, PHP_URL_PATH) ?: '/');

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                $this->call($route['handler']);
                return;
            }
        }

        (new ErrorController())->notFound();
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private function call(callable|array $handler): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            (new $class())->{$method}();
            return;
        }

        $handler();
    }
}
