<?php

declare(strict_types=1);

use App\Controllers\AiPlaygroundController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\EditorialRuleController;
use App\Controllers\GoalController;
use App\Controllers\HomeController;
use App\Controllers\ProductionController;
use App\Controllers\SiteController;
use App\Controllers\UserController;
use App\Controllers\WordPressConnectionController;
use App\Router;

/**
 * Rotas. `auth: true` exige sessão; `admin: true` exige perfil ADMIN.
 * Rotas com {param} devem vir DEPOIS das literais de mesma profundidade
 * (ex.: /sites/new antes de /sites/{id}).
 * O acesso por site (Redator-Chefe só nos vinculados) é validado no controller
 * via Controller::requireSite().
 */
return static function (Router $router): void {
    // Autenticação
    $router->add('GET', '/login', [AuthController::class, 'showLogin']);
    $router->add('POST', '/login', [AuthController::class, 'login']);
    $router->add('POST', '/logout', [AuthController::class, 'logout']);

    // Aplicação
    $router->add('GET', '/', [HomeController::class, 'index'], auth: true);
    $router->add('GET', '/api/health/db', [HomeController::class, 'databaseHealth'], auth: true);

    // Usuários (somente ADMIN)
    $router->add('GET',  '/users',           [UserController::class, 'index'],  admin: true);
    $router->add('GET',  '/users/new',       [UserController::class, 'create'], admin: true);
    $router->add('POST', '/users',           [UserController::class, 'store'],  admin: true);
    $router->add('GET',  '/users/{id}/edit', [UserController::class, 'edit'],   admin: true);
    $router->add('POST', '/users/{id}',      [UserController::class, 'update'], admin: true);

    // Sites — lista e área de trabalho (ADMIN + Redator-Chefe vinculado)
    $router->add('GET',  '/sites',           [SiteController::class, 'index'],  auth: true);
    $router->add('GET',  '/sites/new',       [SiteController::class, 'create'], admin: true);
    $router->add('POST', '/sites',           [SiteController::class, 'store'],  admin: true);
    $router->add('GET',  '/sites/{id}/edit', [SiteController::class, 'edit'],   admin: true);
    $router->add('POST', '/sites/{id}',      [SiteController::class, 'update'], admin: true);
    $router->add('GET',  '/sites/{id}',      [SiteController::class, 'show'],   auth: true);

    // Categorias do site
    $router->add('GET',  '/sites/{id}/categories',            [CategoryController::class, 'index'],   auth: true);
    $router->add('GET',  '/sites/{id}/categories/new',        [CategoryController::class, 'create'],  auth: true);
    $router->add('POST', '/sites/{id}/categories',            [CategoryController::class, 'store'],   auth: true);
    $router->add('GET',  '/sites/{id}/categories/{cid}/edit', [CategoryController::class, 'edit'],    auth: true);
    $router->add('POST', '/sites/{id}/categories/{cid}',      [CategoryController::class, 'update'],  auth: true);
    $router->add('POST', '/sites/{id}/categories/{cid}/delete', [CategoryController::class, 'destroy'], auth: true);

    // Interesses / não-interesses do site
    $router->add('GET',  '/sites/{id}/rules',             [EditorialRuleController::class, 'index'],   auth: true);
    $router->add('GET',  '/sites/{id}/rules/new',         [EditorialRuleController::class, 'create'],  auth: true);
    $router->add('POST', '/sites/{id}/rules',             [EditorialRuleController::class, 'store'],   auth: true);
    $router->add('GET',  '/sites/{id}/rules/{rid}/edit',  [EditorialRuleController::class, 'edit'],     auth: true);
    $router->add('POST', '/sites/{id}/rules/{rid}',       [EditorialRuleController::class, 'update'],   auth: true);
    $router->add('POST', '/sites/{id}/rules/{rid}/delete', [EditorialRuleController::class, 'destroy'], auth: true);

    // Metas editoriais do site
    $router->add('GET',  '/sites/{id}/goals',             [GoalController::class, 'index'],   auth: true);
    $router->add('GET',  '/sites/{id}/goals/new',         [GoalController::class, 'create'],  auth: true);
    $router->add('POST', '/sites/{id}/goals',             [GoalController::class, 'store'],   auth: true);
    $router->add('GET',  '/sites/{id}/goals/{gid}/edit',  [GoalController::class, 'edit'],    auth: true);
    $router->add('POST', '/sites/{id}/goals/{gid}',       [GoalController::class, 'update'],  auth: true);
    $router->add('POST', '/sites/{id}/goals/{gid}/delete', [GoalController::class, 'destroy'], auth: true);

    // Conexão WordPress do site (RF-016 — somente ADMIN)
    $router->add('GET',  '/sites/{id}/wordpress',        [WordPressConnectionController::class, 'edit'],    admin: true);
    $router->add('POST', '/sites/{id}/wordpress',        [WordPressConnectionController::class, 'update'],  admin: true);
    $router->add('POST', '/sites/{id}/wordpress/test',   [WordPressConnectionController::class, 'test'],    admin: true);
    $router->add('POST', '/sites/{id}/wordpress/delete', [WordPressConnectionController::class, 'destroy'], admin: true);

    // Playground de IA do site (somente ADMIN — cada execução é chamada real ao Gemini)
    $router->add('GET',  '/sites/{id}/ai-playground', [AiPlaygroundController::class, 'index'], admin: true);
    $router->add('POST', '/sites/{id}/ai-playground', [AiPlaygroundController::class, 'run'],   admin: true);

    // Produção de artigos pela IA
    $router->add('GET',  '/sites/{id}/production',              [ProductionController::class, 'index'],    auth: true);
    $router->add('POST', '/sites/{id}/production/generate',     [ProductionController::class, 'generate'], auth: true);
    $router->add('GET',  '/sites/{id}/production/{aid}',        [ProductionController::class, 'show'],     auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/delete', [ProductionController::class, 'destroy'],  auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/approve', [ProductionController::class, 'approve'], auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/reject',  [ProductionController::class, 'reject'],  auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/regenerate', [ProductionController::class, 'regenerate'], auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/images/select',      [ProductionController::class, 'selectImage'], auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/images/{iid}/delete', [ProductionController::class, 'deleteImage'], auth: true);
};
