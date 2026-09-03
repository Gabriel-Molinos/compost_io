<?php

declare(strict_types=1);

use App\Services\AuthService;

/** @var array<string, mixed> $site */
/** @var string $activeTab */

/**
 * Não renderiza nada — só monta $tabs. A navegação em si (seções do site)
 * mora na sidebar (layout/_nav.php), fora do fluxo de $content; PHP
 * compartilha o escopo de `require` entre este arquivo, a View que o chama
 * e o layout (mesma técnica que já existia pra $content chegar ao layout).
 */
$tabs = [
    ['overview',   'Visão geral', '/sites/' . $site['id'],                true],
    ['categories', 'Categorias',  '/sites/' . $site['id'] . '/categories', true],
    ['rules',      'Interesses',  '/sites/' . $site['id'] . '/rules',      true],
    ['memory',     'Memória',     '/sites/' . $site['id'] . '/memory',     true],
    ['goals',      'Metas',       '/sites/' . $site['id'] . '/goals',      true],
    ['production', 'Produção',    '/sites/' . $site['id'] . '/production', true],
    ['calendar',   'Calendário',  '/sites/' . $site['id'] . '/calendar',   true],
    ['reports',    'Relatórios',  '/sites/' . $site['id'] . '/reports',    true],
    ['intelligence', 'Inteligência', '/sites/' . $site['id'] . '/intelligence', true],
    ['config',     'Configuração', '/sites/' . $site['id'] . '/edit',      AuthService::isAdmin()],
    ['wordpress',  'WordPress',   '/sites/' . $site['id'] . '/wordpress', AuthService::isAdmin()],
    ['ai',         'IA (teste)',  '/sites/' . $site['id'] . '/ai-playground', AuthService::isAdmin()],
];
