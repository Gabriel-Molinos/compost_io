<?php

declare(strict_types=1);

namespace App;

use App\Controllers\ErrorController;
use App\Services\AuthService;
use App\Support\Http;

/**
 * Roteador manual (sem framework). Mapeia método + caminho exato para um
 * handler [Classe::class, 'metodo'] ou um callable.
 * Ver docs/technical/arquitetura.md §10.
 */
final class Router
{
    /** @var array<int, array{method: string, path: string, handler: callable|array, auth: bool}> */
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler, bool $auth = false): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'path'    => $this->normalize($path),
            'handler' => $handler,
            'auth'    => $auth,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = $this->normalize(parse_url($uri, PHP_URL_PATH) ?: '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || $route['path'] !== $path) {
                continue;
            }

            if ($route['auth'] && !AuthService::check()) {
                if ($method === 'GET') {
                    Http::redirect('/login');
                }

                http_response_code(401);
                return;
            }

            $this->call($route['handler']);
            return;
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
