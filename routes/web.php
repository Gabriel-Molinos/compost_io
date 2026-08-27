<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Router;

/**
 * Definição de rotas. A maioria responde HTML; endpoints internos consumidos
 * por JavaScript vanilla respondem JSON (docs/technical/requisitos.md §68).
 */
return static function (Router $router): void {
    $router->add('GET', '/', [HomeController::class, 'index']);
    $router->add('GET', '/api/health/db', [HomeController::class, 'databaseHealth']);
};
