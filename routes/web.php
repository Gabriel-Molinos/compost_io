<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Router;

/**
 * Definição de rotas. A maioria responde HTML; endpoints internos consumidos
 * por JavaScript vanilla respondem JSON (docs/technical/requisitos.md §68).
 * O 4º argumento (auth) marca rotas que exigem sessão.
 */
return static function (Router $router): void {
    // Autenticação
    $router->add('GET', '/login', [AuthController::class, 'showLogin']);
    $router->add('POST', '/login', [AuthController::class, 'login']);
    $router->add('POST', '/logout', [AuthController::class, 'logout']);

    // Aplicação (exige login)
    $router->add('GET', '/', [HomeController::class, 'index'], auth: true);
    $router->add('GET', '/api/health/db', [HomeController::class, 'databaseHealth'], auth: true);
};
