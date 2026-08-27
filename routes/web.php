<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\SiteController;
use App\Controllers\UserController;
use App\Router;

/**
 * Rotas. `auth: true` exige sessão; `admin: true` exige perfil ADMIN.
 * A maioria responde HTML; endpoints de JS vanilla respondem JSON (requisitos §68).
 */
return static function (Router $router): void {
    // Autenticação
    $router->add('GET', '/login', [AuthController::class, 'showLogin']);
    $router->add('POST', '/login', [AuthController::class, 'login']);
    $router->add('POST', '/logout', [AuthController::class, 'logout']);

    // Aplicação
    $router->add('GET', '/', [HomeController::class, 'index'], auth: true);
    $router->add('GET', '/api/health/db', [HomeController::class, 'databaseHealth'], auth: true);

    // Sites (somente ADMIN)
    $router->add('GET',  '/sites',            [SiteController::class, 'index'],  admin: true);
    $router->add('GET',  '/sites/new',        [SiteController::class, 'create'], admin: true);
    $router->add('POST', '/sites',            [SiteController::class, 'store'],  admin: true);
    $router->add('GET',  '/sites/{id}/edit',  [SiteController::class, 'edit'],   admin: true);
    $router->add('POST', '/sites/{id}',       [SiteController::class, 'update'], admin: true);

    // Usuários (somente ADMIN)
    $router->add('GET',  '/users',            [UserController::class, 'index'],  admin: true);
    $router->add('GET',  '/users/new',        [UserController::class, 'create'], admin: true);
    $router->add('POST', '/users',            [UserController::class, 'store'],  admin: true);
    $router->add('GET',  '/users/{id}/edit',  [UserController::class, 'edit'],   admin: true);
    $router->add('POST', '/users/{id}',       [UserController::class, 'update'], admin: true);
};
