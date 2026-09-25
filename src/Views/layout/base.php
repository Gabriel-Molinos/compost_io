<?php

declare(strict_types=1);

use App\Services\AuthService;
use App\Services\SidebarService;
use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\Support\Pixelito;
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
    // Regras de compliance — qualquer pessoa que gera ou aprova post precisa
    // conhecer, então fica visível pra todo mundo logado, não só ADMIN.
    $globalNav[] = ['/compliance', 'Regras de compliance', 'shield'];
}

// Tutorial guiado (layout/_dial.php + assets/js/tour.js) — só pra Redator-Chefe
// (pedido do responsável, 2026-09-08) e só quando tem pelo menos 1 site
// vinculado (o primeiro passo depois da tela inicial abre um site de
// verdade; sem site nenhum pra abrir, o tour não tem pra onde ir).
// Contagens da sidebar numa consulta só (SidebarService — cada ida ao banco custa ~280 ms).
$sidebar = $authUser !== null
    ? (new SidebarService())->counts((int) $authUser['id'], $authUser['role'] === 'ADMIN')
    : ['unread' => 0, 'pendingFeedback' => 0, 'sitesTotal' => 0, 'firstSiteId' => null];
$tourSiteId = $authUser !== null && $authUser['role'] === 'REDATOR_CHEFE' ? $sidebar['firstSiteId'] : null;
if ($tourSiteId !== null) {
    $globalNav[] = ['/?tour=1', 'Tutorial', 'help'];
}

$showNotifications = $authUser !== null;
$unreadNotifications = $sidebar['unread'];

// Feedback geral sobre a plataforma — qualquer usuário logado vê o link;
// o número no chip é só pro ADMIN (quantos ainda não foram "vistos"), o
// Redator-Chefe não tem nada pra "revisar" aqui, só enviar.
$showFeedback = $authUser !== null;
$pendingFeedback = $sidebar['pendingFeedback'];

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
    $quickSites = (new SidebarService())->quickSites((int) $authUser['id'], $authUser['role'] === 'ADMIN');
    $quickSitesTotal = $sidebar['sitesTotal'];
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
    <script src="<?= View::e(View::asset('assets/js/btn-fx.js')) ?>" defer></script>
    <script src="<?= View::e(View::asset('assets/js/image-lightbox.js')) ?>" defer></script>
    <script src="<?= View::e(View::asset('assets/js/image-carousel.js')) ?>" defer></script>
    <script src="<?= View::e(View::asset('assets/js/avatar-preview.js')) ?>" defer></script>
    <script src="<?= View::e(View::asset('assets/js/footer-clock.js')) ?>" defer></script>
    <script src="<?= View::e(View::asset('assets/js/veil.js')) ?>" defer></script>
    <script src="<?= View::e(View::asset('assets/js/dial.js')) ?>" defer></script>
    <?php if ($authUser !== null): ?>
        <?php // Pixelito (mascote): mapa tipo→expressão vindo de App\Support\Pixelito — o JS nunca tem uma 2ª cópia. ?>
        <script>window.COMPOST_PIXELITO = <?= json_encode(Pixelito::jsConfig(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
        <script src="<?= View::e(View::asset('assets/js/notification-toast.js')) ?>" defer></script>
        <script src="<?= View::e(View::asset('assets/js/pixelito.js')) ?>" defer></script>
    <?php endif; ?>
    <?php if ($tourSiteId !== null): ?>
        <script>window.COMPOST_TOUR_SITE_ID = <?= (int) $tourSiteId ?>;</script>
        <?php // Tutorial em andamento (ou pedido por ?tour=1): o Pixelito do canto já nasce escondido — ele está no balão do tour (tour.js). ?>
        <script>try { if (sessionStorage.getItem('compost_tour_step') !== null || /[?&]tour=1(&|$)/.test(location.search)) { document.documentElement.classList.add('pixelito-away-tour'); } } catch (e) {}</script>
        <script src="<?= View::e(View::asset('assets/js/tour.js')) ?>" defer></script>
    <?php endif; ?>

    <?php require __DIR__ . '/_veil_head.php'; ?>

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
        <?php // Véu de transição: DENTRO do wrapper pra ficar abaixo do dial (z-index) e cobrir também o fundo em volta do arco. ?>
        <div class="page-veil" aria-hidden="true"></div>
        <?php if ($authUser !== null): ?>
            <?php // Pop-ups de notificação nova (assets/js/notification-toast.js, pedido
                  // 2026-09-22) — região só, JS injeta/remove os cartões. aria-live="polite":
                  // leitor de tela anuncia sem interromper o que já estava sendo lido. ?>
            <div id="notif-toast-root" class="notif-toast-root" aria-live="polite" aria-atomic="false"></div>

            <?php // Sidebar radial (dial): coluna fixa no desktop, gaveta no celular. Só o conteúdo ao lado (<main>) rola. ?>
            <?php require __DIR__ . '/_dial.php'; ?>
            <div class="dial-backdrop" data-dial-toggle aria-hidden="true"></div>
            <button type="button" class="dial-fab" data-dial-toggle aria-label="Abrir o menu" title="Abrir o menu">
                <?= Icon::nav('menu') ?>
            </button>

            <?php // Pixelito: ajudante do canto inferior direito (guia de dúvidas — layout/_pixelito.php). ?>
            <?php require __DIR__ . '/_pixelito.php'; ?>
        <?php endif; ?>

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            <!-- Topo compacto (telas < lg): só a marca — o menu é o botão redondo à esquerda (gaveta do dial). -->
            <header class="flex shrink-0 items-center justify-end gap-4 border-b border-border px-4 py-3 pl-16 lg:hidden">
                <a href="/" class="flex items-center gap-2.5">
                    <img src="/assets/brand/icon.webp" alt="" aria-hidden="true" class="brand-icon h-6 w-6 shrink-0">
                    <img src="/assets/brand/wordmark.png" alt="COMPOST" class="h-4 w-auto">
                </a>
            </header>

            <?php // pb-24: folga no fim da página pro botão flutuante do Pixelito não cobrir o último conteúdo. ?>
            <main id="conteudo" class="flex-1 overflow-y-auto px-4 py-6 pb-24 sm:px-6 sm:py-8 sm:pb-24 lg:px-10">
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
