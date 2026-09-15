<?php

declare(strict_types=1);

use App\Controllers\AiPlaygroundController;
use App\Controllers\AuthController;
use App\Controllers\CalendarController;
use App\Controllers\CategoryController;
use App\Controllers\EditorialMemoryController;
use App\Controllers\EditorialRuleController;
use App\Controllers\GoalController;
use App\Controllers\HomeController;
use App\Controllers\IntelligenceController;
use App\Controllers\LinksController;
use App\Controllers\SiteSourceController;
use App\Controllers\NotificationController;
use App\Controllers\ProductionController;
use App\Controllers\ProfileController;
use App\Controllers\ReportController;
use App\Controllers\ScheduleController;
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
    // Redirect URI do Login com Google (GIS, modo redirect) — caminho exigido
    // pela URI já cadastrada no Google Cloud Console (achado real 2026-09-15).
    $router->add('POST', '/oauth/callback', [AuthController::class, 'googleCallback']);

    // Aplicação
    $router->add('GET', '/', [HomeController::class, 'index'], auth: true);
    $router->add('GET', '/api/health/db', [HomeController::class, 'databaseHealth'], auth: true);

    // Meu perfil (qualquer usuário logado — troca a própria foto e o próprio
    // nome, nunca de outro usuário; e-mail/senha ficam só com o admin em /users)
    $router->add('GET',  '/profile',        [ProfileController::class, 'edit'],         auth: true);
    $router->add('POST', '/profile/name',   [ProfileController::class, 'updateName'],   auth: true);
    $router->add('POST', '/profile/avatar', [ProfileController::class, 'updateAvatar'], auth: true);

    // Notificações (qualquer usuário logado — sempre só as próprias)
    $router->add('GET',  '/notifications',           [NotificationController::class, 'index'],        auth: true);
    $router->add('POST', '/notifications/read-all',  [NotificationController::class, 'markAllRead'],  auth: true);
    $router->add('POST', '/notifications/{id}/open', [NotificationController::class, 'open'],          auth: true);

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
    $router->add('POST', '/sites/{id}',        [SiteController::class, 'update'],  admin: true);
    $router->add('POST', '/sites/{id}/delete', [SiteController::class, 'destroy'], admin: true);
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

    $router->add('GET',  '/sites/{id}/memory',              [EditorialMemoryController::class, 'index'],   auth: true);
    $router->add('POST', '/sites/{id}/memory',               [EditorialMemoryController::class, 'store'],   auth: true);
    $router->add('POST', '/sites/{id}/memory/promote',       [EditorialMemoryController::class, 'promote'], auth: true);
    $router->add('POST', '/sites/{id}/memory/{mid}',         [EditorialMemoryController::class, 'update'],  auth: true);
    $router->add('POST', '/sites/{id}/memory/{mid}/toggle',  [EditorialMemoryController::class, 'toggle'],  auth: true);
    $router->add('POST', '/sites/{id}/memory/{mid}/delete',  [EditorialMemoryController::class, 'destroy'], auth: true);

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
    $router->add('POST', '/sites/{id}/wordpress/test',            [WordPressConnectionController::class, 'test'],           admin: true);
    $router->add('POST', '/sites/{id}/wordpress/delete',          [WordPressConnectionController::class, 'destroy'],        admin: true);
    $router->add('POST', '/sites/{id}/wordpress/sync-authors',    [WordPressConnectionController::class, 'syncAuthors'],    admin: true);
    $router->add('POST', '/sites/{id}/wordpress/sync-categories', [WordPressConnectionController::class, 'syncCategories'], admin: true);

    // Playground de IA do site (somente ADMIN — cada execução é chamada real ao Gemini)
    $router->add('GET',  '/sites/{id}/ai-playground', [AiPlaygroundController::class, 'index'], admin: true);
    $router->add('POST', '/sites/{id}/ai-playground', [AiPlaygroundController::class, 'run'],   admin: true);

    // Calendário editorial (agendamentos do site)
    $router->add('GET', '/sites/{id}/calendar', [CalendarController::class, 'index'], auth: true);
    $router->add('POST', '/sites/{id}/calendar/reschedule', [CalendarController::class, 'reschedule'], auth: true);

    // Relatórios do site (RF-013)
    $router->add('GET', '/sites/{id}/reports', [ReportController::class, 'index'], auth: true);

    // Centro de Inteligência Editorial do site (RF-014 — geração sob demanda, chamada paga)
    $router->add('GET',  '/sites/{id}/intelligence',          [IntelligenceController::class, 'index'],    auth: true);
    $router->add('POST', '/sites/{id}/intelligence/generate', [IntelligenceController::class, 'generate'], auth: true);

    // Produção de artigos pela IA
    $router->add('GET',  '/sites/{id}/production',              [ProductionController::class, 'index'],    auth: true);
    $router->add('POST', '/sites/{id}/production/generate',     [ProductionController::class, 'generate'], auth: true);
    $router->add('GET',  '/sites/{id}/production/{aid}',        [ProductionController::class, 'show'],     auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/delete', [ProductionController::class, 'destroy'],  auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/approve', [ProductionController::class, 'approve'], auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/reject',  [ProductionController::class, 'reject'],  auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/regenerate', [ProductionController::class, 'regenerate'], auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/content',   [ProductionController::class, 'updateContent'], auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/sources/suggest', [ProductionController::class, 'suggestExternalLinks'], auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/internal-links/suggest', [ProductionController::class, 'suggestInternalLinks'], auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/images/select',      [ProductionController::class, 'selectImage'], auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/images/{iid}/delete', [ProductionController::class, 'deleteImage'], auth: true);

    // Agendamento de publicação (RF-011)
    $router->add('POST', '/sites/{id}/production/{aid}/schedule',        [ScheduleController::class, 'store'],   auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/schedule/update', [ScheduleController::class, 'update'],  auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/schedule/cancel', [ScheduleController::class, 'destroy'], auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/schedule/publish',   [ScheduleController::class, 'publish'],   auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/schedule/republish', [ScheduleController::class, 'republish'], auth: true);
    $router->add('POST', '/sites/{id}/production/{aid}/schedule/retract',   [ScheduleController::class, 'retract'],   auth: true);

    // Central de Links — corrigir link ambíguo/link rot sem editar HTML
    $router->add('GET',  '/sites/{id}/links',                 [LinksController::class, 'index'],   auth: true);
    $router->add('POST', '/sites/{id}/links/{aid}/remove',    [LinksController::class, 'remove'],  auth: true);
    $router->add('POST', '/sites/{id}/links/{aid}/replace',   [LinksController::class, 'replace'], auth: true);
    $router->add('POST', '/sites/{id}/links/{aid}/confirm',   [LinksController::class, 'confirm'], auth: true);
    $router->add('POST', '/sites/{id}/links/{aid}/backlink/apply',   [LinksController::class, 'applyBacklink'],   auth: true);
    $router->add('POST', '/sites/{id}/links/{aid}/backlink/dismiss', [LinksController::class, 'dismissBacklink'], auth: true);

    // Fontes confiáveis do site — cadastro humano, consultado pelo passo research (docs/ai/research.md)
    $router->add('GET',  '/sites/{id}/sources',              [SiteSourceController::class, 'index'],   auth: true);
    $router->add('POST', '/sites/{id}/sources',              [SiteSourceController::class, 'store'],   auth: true);
    $router->add('POST', '/sites/{id}/sources/{sid}/delete', [SiteSourceController::class, 'destroy'], auth: true);
};
