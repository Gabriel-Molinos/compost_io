<?php

declare(strict_types=1);

namespace App;

use App\Controllers\ErrorController;
use App\Services\AuthService;
use App\Support\Http;

/**
 * Roteador manual (sem framework). Caminho pode ter parâmetros: `/sites/{id}/edit`.
 * O 4º/5º argumentos marcam rotas que exigem sessão / perfil ADMIN.
 * Ver docs/technical/arquitetura.md §10.
 */
final class Router
{
    /** @var array<int, array{method:string, regex:string, handler:callable|array, auth:bool, admin:bool}> */
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler, bool $auth = false, bool $admin = false): void
    {
        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $this->normalize($path));

        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'handler' => $handler,
            'auth'    => $auth || $admin,
            'admin'   => $admin,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = $this->normalize(parse_url($uri, PHP_URL_PATH) ?: '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method || !preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            if ($route['auth'] && !AuthService::check()) {
                $method === 'GET' ? Http::redirect('/login') : $this->abort(401);
                return;
            }

            if ($route['admin'] && !AuthService::isAdmin()) {
                $this->abort(403);
                return;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            $this->call($route['handler'], array_values($params));
            return;
        }

        (new ErrorController())->notFound();
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /** @param list<string> $params */
    private function call(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            (new $class())->{$method}(...$params);
            return;
        }

        $handler(...$params);
    }

    private function abort(int $status): void
    {
        http_response_code($status);
        (new ErrorController())->show($status);
    }
}
