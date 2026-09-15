<?php

declare(strict_types=1);

use App\Services\AuthService;
use App\Services\NotificationService;
use App\Services\SiteService;
use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\Support\Session;
use App\View;

/** @var string $content */
/** @var string $title */
/** @var int|null $metaRefresh */
/** @var array<string,mixed>|null $site  — setado por sites/_tabs.php quando a página é de um site */
/** @var list<array{0:string,1:string,2:string,3:bool}>|null $tabs — idem */

$authUser = AuthService::user();
$currentPath = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
$flashSuccess = Session::pullFlash('success');
$flashError = Session::pullFlash('error');

// $site/$tabs/$activeTab só existem quando a View (via sites/_tabs.php) já
// rodou dentro deste mesmo escopo de require — mesma técnica que traz
// $content até aqui. Nas páginas fora de um site (Início, Sites, Usuários,
// login) eles simplesmente não existem; ??= evita "undefined variable".
$site ??= null;
$tabs ??= null;
$activeTab ??= null;

$globalNav = [['/', 'Início', 'home']];
if ($authUser !== null) {
    if ($authUser['role'] === 'ADMIN') {
        $globalNav[] = ['/users', 'Usuários', 'users'];
    }
    $globalNav[] = ['/sites', 'Sites', 'sites'];
}

// Tutorial guiado (layout/_nav.php + assets/js/tour.js) — só pra Redator-Chefe
// (pedido do responsável, 2026-09-08) e só quando tem pelo menos 1 site
// vinculado (o primeiro passo depois da tela inicial abre um site de
// verdade; sem site nenhum pra abrir, o tour não tem pra onde ir).
$tourSiteId = $authUser !== null && $authUser['role'] === 'REDATOR_CHEFE'
    ? (new SiteService())->firstIdForUser((int) $authUser['id'])
    : null;
if ($tourSiteId !== null) {
    $globalNav[] = ['/?tour=1', 'Tutorial', 'help'];
}

$showNotifications = $authUser !== null;
$unreadNotifications = $showNotifications ? (new NotificationService())->unreadCount((int) $authUser['id']) : 0;

// "/sites" só ativo na lista em si (match exato) — dentro de um site
// (/sites/{id}/...) quem mostra onde você está é a seção do site logo
// abaixo; com prefixo teria os dois marcados ao mesmo tempo, confuso.
$isActive = static fn (string $href): bool => $href === $currentPath
    || ($href !== '/' && $href !== '/sites' && str_starts_with($currentPath, $href));

$hasSiteNav = $authUser !== null && $site !== null && $tabs !== null;

// Fora do contexto de um site, a sidebar mostra um atalho pros sites do
// usuário (igual "Meus sites" que já existia em home/index.php, só que
// visível em qualquer tela) — com cap (§97 performance, plataforma pensada
// pra 60+ sites) e link "Ver todos" quando passar do cap, em vez de listar
// tudo de uma vez.
$quickSites = [];
$quickSitesTotal = 0;
if ($authUser !== null && !$hasSiteNav) {
    $sites = new SiteService();
    $isAdmin = $authUser['role'] === 'ADMIN';
    $quickSites = $sites->recentForSidebar((int) $authUser['id'], $isAdmin);
    $quickSitesTotal = $isAdmin ? $sites->countAll() : $sites->countForUser((int) $authUser['id']);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="csrf-token" content="<?= View::e(Csrf::token()) ?>">
    <title><?= View::e($title) ?> · COMPOST</title>
    <?php if (!empty($metaRefresh)): ?>
        <meta http-equiv="refresh" content="<?= (int) $metaRefresh ?>">
    <?php endif; ?>

    <link rel="icon" type="image/png" sizes="32x32" href="/assets/brand/favicon-32.png">
    <link rel="apple-touch-icon" href="/assets/brand/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300;400;500;600;700&family=Orbitron:wght@500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= View::e(View::asset('assets/css/app.css')) ?>">
    <script src="<?= View::e(View::asset('assets/js/select-enhance.js')) ?>" defer></script>
    <script src="<?= View::e(View::asset('assets/js/confirm-dialog.js')) ?>" defer></script>
    <script src="<?= View::e(View::asset('assets/js/image-lightbox.js')) ?>" defer></script>
    <script src="<?= View::e(View::asset('assets/js/image-carousel.js')) ?>" defer></script>
    <script src="<?= View::e(View::asset('assets/js/avatar-preview.js')) ?>" defer></script>
    <script src="<?= View::e(View::asset('assets/js/footer-clock.js')) ?>" defer></script>
    <?php if ($tourSiteId !== null): ?>
        <script>window.COMPOST_TOUR_SITE_ID = <?= (int) $tourSiteId ?>;</script>
        <script src="<?= View::e(View::asset('assets/js/tour.js')) ?>" defer></script>
    <?php endif; ?>

    <style>
        /* Sem border-radius aqui: "outline-radius" não existe em CSS — essa
           propriedade cai no ELEMENTO em foco, não no contorno, sobrescrevendo
           qualquer rounded-* da própria View (achado real: os inputs em pill
           do /login viravam quadrados ao focar). */
        :focus-visible { outline: 2px solid #7FE8FF; outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; }
        }
        .skip-link {
            position: absolute; left: 0; top: 0; transform: translateY(-120%);
            background: #00D0F0; color: #050B0F; padding: .5rem 1rem; font-weight: 600;
            transition: transform .15s ease;
            z-index: 50;
        }
        .skip-link:focus { transform: translateY(0); }
    </style>
</head>
<body class="app-bg h-screen overflow-hidden text-text-primary font-sans antialiased">
    <a href="#conteudo" class="skip-link">Pular para o conteúdo</a>

    <div class="relative z-10 flex h-screen">
        <!-- Sidebar fixa (telas ≥ lg) — não rola junto com a página, só a
             navegação dentro dela (se a lista de seções não couber) e o
             conteúdo principal ao lado (ver <main> abaixo) têm scroll próprio. -->
        <aside class="hidden w-64 shrink-0 flex-col border-r border-border bg-surface/90 py-5 lg:flex">
            <a href="/" class="flex items-center gap-2.5 px-5">
                <img src="/assets/brand/icon.webp" alt="" aria-hidden="true" class="brand-icon h-7 w-7 shrink-0">
                <img src="/assets/brand/wordmark.png" alt="COMPOST" class="h-4 w-auto">
            </a>

            <?php if ($authUser !== null): ?>
                <div class="mx-4 mt-5 flex items-center gap-2.5 rounded-lg border border-border bg-surface-2/50 p-2.5">
                    <a href="/profile" class="flex min-w-0 flex-1 items-center gap-2.5 rounded-md" title="Meu perfil">
                        <?= Avatar::html($authUser['avatar_path'] ?? null, $authUser['name'], size: 'h-9 w-9', radius: 'rounded-md', textSize: 'text-sm') ?>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-text-primary"><?= View::e($authUser['name']) ?></span>
                            <span class="block truncate text-xs text-text-muted"><?= View::e(Labels::role($authUser['role'])) ?></span>
                        </span>
                    </a>
                    <form method="post" action="/logout">
                        <?= Csrf::field() ?>
                        <button type="submit" title="Sair"
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-text-secondary hover:bg-surface hover:text-cyan">
                            <?= Icon::nav('logout') ?>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="mt-6 flex-1 overflow-y-auto px-4">
                <?php require __DIR__ . '/_nav.php'; ?>
            </div>

            <div class="border-t border-border px-4 pt-4 text-[11px] uppercase tracking-wide text-text-muted">
                <div class="flex items-center justify-between">
                    <span class="font-mono tabular-nums text-text-secondary" data-clock>00:00:00</span>
                    <span class="flex items-center gap-1.5">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-cyan shadow-[0_0_6px_rgba(0,208,240,.8)]"></span>
                        COMPOST
                    </span>
                </div>
                <p class="mt-1 font-mono text-text-secondary"><?= View::e(View::todayShort()) ?></p>
            </div>
        </aside>

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <!-- Topo compacto (telas < lg) — a navegação mora num <details>, sem depender de JS. -->
            <header class="flex shrink-0 items-center justify-between gap-4 border-b border-border px-4 py-3 lg:hidden">
                <a href="/" class="flex items-center gap-2.5">
                    <img src="/assets/brand/icon.webp" alt="" aria-hidden="true" class="brand-icon h-6 w-6 shrink-0">
                    <img src="/assets/brand/wordmark.png" alt="COMPOST" class="h-4 w-auto">
                </a>
                <?php if ($authUser !== null): ?>
                    <details class="relative">
                        <summary class="rounded-md border border-border px-3 py-1.5 text-sm text-text-secondary">Menu</summary>
                        <div class="absolute right-0 z-40 mt-2 w-64 rounded-lg border border-border bg-surface p-4 shadow-2xl">
                            <div class="flex items-center gap-2.5 rounded-lg border border-border bg-surface-2/50 p-2.5">
                                <a href="/profile" class="flex min-w-0 flex-1 items-center gap-2.5 rounded-md" title="Meu perfil">
                                    <?= Avatar::html($authUser['avatar_path'] ?? null, $authUser['name'], size: 'h-9 w-9', radius: 'rounded-md', textSize: 'text-sm') ?>
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-semibold text-text-primary"><?= View::e($authUser['name']) ?></span>
                                        <span class="block truncate text-xs text-text-muted"><?= View::e(Labels::role($authUser['role'])) ?></span>
                                    </span>
                                </a>
                                <form method="post" action="/logout">
                                    <?= Csrf::field() ?>
                                    <button type="submit" title="Sair"
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-text-secondary hover:bg-surface hover:text-cyan">
                                        <?= Icon::nav('logout') ?>
                                    </button>
                                </form>
                            </div>
                            <div class="mt-4 border-t border-border pt-4">
                                <?php require __DIR__ . '/_nav.php'; ?>
                            </div>
                            <div class="mt-4 border-t border-border pt-4 text-[11px] uppercase tracking-wide text-text-muted">
                                <div class="flex items-center justify-between">
                                    <span class="font-mono tabular-nums text-text-secondary" data-clock>00:00:00</span>
                                    <span class="flex items-center gap-1.5">
                                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-cyan shadow-[0_0_6px_rgba(0,208,240,.8)]"></span>
                                        COMPOST
                                    </span>
                                </div>
                                <p class="mt-1 font-mono text-text-secondary"><?= View::e(View::todayShort()) ?></p>
                            </div>
                        </div>
                    </details>
                <?php endif; ?>
            </header>

            <main id="conteudo" class="flex-1 overflow-y-auto px-4 py-6 sm:px-6 sm:py-8 lg:px-10">
                <?php if ($flashSuccess !== null): ?>
                    <p role="status" class="mb-6 rounded-md border border-success/40 bg-success/10 px-3 py-2 text-sm text-success">
                        <?= View::e($flashSuccess) ?>
                    </p>
                <?php endif; ?>
                <?php if ($flashError !== null): ?>
                    <p role="alert" class="mb-6 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
                        <?= View::e($flashError) ?>
                    </p>
                <?php endif; ?>

                <div class="mx-auto max-w-6xl">
                    <?= $content ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
