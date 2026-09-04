<?php

declare(strict_types=1);

use App\Services\AuthService;
use App\Services\SiteService;
use App\Support\Csrf;
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

    <style>
        :focus-visible { outline: 2px solid #7FE8FF; outline-offset: 2px; border-radius: 2px; }
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
<body class="app-bg min-h-screen text-text-primary font-sans antialiased">
    <a href="#conteudo" class="skip-link">Pular para o conteúdo</a>

    <div class="flex min-h-screen">
        <!-- Sidebar fixa (telas ≥ lg) -->
        <aside class="hidden w-64 shrink-0 flex-col border-r border-border bg-surface/60 px-4 py-6 lg:flex">
            <a href="/" class="flex items-center gap-2.5 px-2">
                <img src="/assets/brand/icon.webp" alt="" aria-hidden="true" class="brand-icon h-6 w-6 shrink-0">
                <img src="/assets/brand/wordmark.png" alt="COMPOST" class="h-4 w-auto">
            </a>

            <div class="mt-8 flex-1 overflow-y-auto">
                <?php require __DIR__ . '/_nav.php'; ?>
            </div>

            <?php if ($authUser !== null): ?>
                <div class="mt-6 border-t border-border pt-4">
                    <p class="truncate px-2 text-xs text-text-secondary"><?= View::e($authUser['email']) ?></p>
                    <form method="post" action="/logout" class="mt-2">
                        <?= Csrf::field() ?>
                        <button type="submit"
                                class="w-full rounded-md border border-border px-3 py-1.5 text-left text-sm text-text-secondary hover:border-cyan hover:text-text-primary">
                            Sair
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Topo compacto (telas < lg) — a navegação mora num <details>, sem depender de JS. -->
            <header class="flex items-center justify-between gap-4 border-b border-border px-4 py-3 lg:hidden">
                <a href="/" class="flex items-center gap-2.5">
                    <img src="/assets/brand/icon.webp" alt="" aria-hidden="true" class="brand-icon h-6 w-6 shrink-0">
                    <img src="/assets/brand/wordmark.png" alt="COMPOST" class="h-4 w-auto">
                </a>
                <?php if ($authUser !== null): ?>
                    <details class="relative">
                        <summary class="rounded-md border border-border px-3 py-1.5 text-sm text-text-secondary">Menu</summary>
                        <div class="absolute right-0 z-40 mt-2 w-64 rounded-lg border border-border bg-surface p-4 shadow-2xl">
                            <?php require __DIR__ . '/_nav.php'; ?>
                            <div class="mt-6 border-t border-border pt-4">
                                <p class="truncate px-2 text-xs text-text-secondary"><?= View::e($authUser['email']) ?></p>
                                <form method="post" action="/logout" class="mt-2">
                                    <?= Csrf::field() ?>
                                    <button type="submit"
                                            class="w-full rounded-md border border-border px-3 py-1.5 text-left text-sm text-text-secondary hover:border-cyan">
                                        Sair
                                    </button>
                                </form>
                            </div>
                        </div>
                    </details>
                <?php endif; ?>
            </header>

            <main id="conteudo" class="flex-1 px-4 py-6 sm:px-6 sm:py-8 lg:px-10">
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

            <footer class="border-t border-border px-4 py-6 text-xs text-text-muted sm:px-6 lg:px-10">
                COMPOST · <?= View::e(date('Y')) ?>
            </footer>
        </div>
    </div>
</body>
</html>
