<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string,mixed>|null $authUser */
/** @var list<array{0:string,1:string,2:string}> $globalNav — [href, label, ícone] */
/** @var callable(string):bool $isActive */
/** @var bool $hasSiteNav */
/** @var array<string,mixed>|null $site */
/** @var list<array{0:string,1:string,2:string,3:bool}>|null $tabs — [chave, label, href, visível] */
/** @var string|null $activeTab */
/** @var list<array{id:int,name:string,is_active:int}> $quickSites — atalho fora do contexto de um site */
/** @var int $quickSitesTotal */
/** @var int $unreadNotifications */
/** @var bool $showNotifications */
/** @var bool $showFeedback */
/** @var int $pendingFeedback */
/** @var int|null $tourSiteId — site que o tutorial guiado abre a partir daqui (assets/js/tour.js) */

// Sidebar radial (src/styles/input.css, bloco "Sidebar radial"; movimento em
// assets/js/dial.js). Este arquivo só monta o HTML: o disco (avatar, atalhos
// redondos, marca, relógio) e o anel de itens de navegação, que o script coloca
// ao longo do arco e gira. O anel é UMA lista só — navegação global e, logo
// depois, as seções do site atual (ou os atalhos de sites) — cada item carrega
// o nome do seu grupo (data-group) pro rótulo que aparece quando ele está no centro.
//
// Cor por ESCOPO (pedido do responsável, 2026-09-18): a navegação principal é toda ciano e o que é
// de dentro do site (abas do site e atalhos de sites) é violeta — dá pra distinguir de relance onde
// você está. Violeta porque contrasta bem com o ciano, combina com o visual Y2K e não colide com
// verde/âmbar/vermelho, que no app já significam sucesso/aviso/erro. O tom vira `--tone` (ícone,
// hover, holofote, pílula do selecionado). Nomes literais (scanner do Tailwind).
$toneFor = static fn (string $scope): string => $scope === 'main' ? 'nav-tone-cyan' : 'nav-tone-violet';

// Cada grupo começa com um divisor (`sep`) no anel — assim dá pra distinguir a navegação principal
// (Início, Sites…) do que é de DENTRO do site atual (ou dos atalhos de sites). Os itens levam `scope`
// (main | site | sites): os do site têm o ícone quadrado, os principais são redondos.
/** @var list<array<string,mixed>> $ring */
$ring = [['sep' => 'Navegação', 'scope' => 'main', 'sepIcon' => 'home']];
foreach ($globalNav as [$href, $label, $icon]) {
    $ring[] = ['href' => $href, 'label' => $label, 'icon' => $icon, 'active' => $isActive($href), 'group' => 'Navegação', 'scope' => 'main', 'tour' => null, 'letter' => null, 'inactive' => false];
}
$ringTour = null;
if ($hasSiteNav) {
    $ringTour = 'site-tabs';
    $ring[] = ['sep' => 'Site · ' . $site['name'], 'scope' => 'site', 'sepIcon' => 'sites'];
    foreach ($tabs as [$key, $label, $href, $visible]) {
        if (!$visible) {
            continue;
        }
        $ring[] = ['href' => $href, 'label' => $label, 'icon' => $key, 'active' => ($activeTab ?? '') === $key, 'group' => 'Site · ' . $site['name'], 'scope' => 'site', 'tour' => 'tab-' . $key, 'letter' => null, 'inactive' => false];
    }
} elseif ($quickSites !== []) {
    $ringTour = 'quick-sites';
    $ring[] = ['sep' => 'Meus sites', 'scope' => 'sites', 'sepIcon' => 'sites'];
    foreach ($quickSites as $s) {
        $ring[] = [
            'href' => '/sites/' . $s['id'], 'label' => (string) $s['name'], 'icon' => 'sites', 'active' => false, 'group' => 'Meus sites', 'scope' => 'sites',
            'tour' => isset($tourSiteId) && (int) $s['id'] === $tourSiteId ? 'quick-sites-target' : null,
            'letter' => mb_strtoupper(mb_substr(trim((string) $s['name']), 0, 1)) ?: '?', 'inactive' => (int) ($s['is_active'] ?? 1) !== 1,
        ];
    }
    if ($quickSitesTotal > count($quickSites)) {
        $ring[] = ['href' => '/sites', 'label' => 'Ver todos (' . $quickSitesTotal . ')', 'icon' => 'arrow', 'active' => false, 'group' => 'Meus sites', 'scope' => 'sites', 'tour' => null, 'letter' => null, 'inactive' => false];
    }
}

// Atalhos redondos do disco: [href|null, rótulo, ícone, tom, posição no arco (-1/0/1), recuo do arco, selo]
$shortcuts = [];
if ($showNotifications) {
    $shortcuts[] = ['/notifications', 'Notificações', 'bell', 'nav-tone-cyan', -1, '0rem', $unreadNotifications];
}
if ($showFeedback) {
    $shortcuts[] = ['/feedback', 'Feedback', 'feedback', 'nav-tone-cyan', 0, '1rem', $pendingFeedback];
}
?>
<aside class="dial" data-dial aria-label="Navegação principal">
    <div class="dial-panel">
        <span class="dial-band" aria-hidden="true"></span>
        <span class="dial-ticks" data-dial-ticks aria-hidden="true"></span>
        <span class="dial-disc" aria-hidden="true"></span>
        <span class="dial-marker" aria-hidden="true"></span>

        <a href="/" class="dial-brand" aria-label="COMPOST — início">
            <img src="/assets/brand/icon.webp" alt="" aria-hidden="true" class="brand-icon h-8 w-8 shrink-0">
            <img src="/assets/brand/wordmark.png" alt="COMPOST" class="h-3.5 w-auto">
        </a>

        <?php if ($authUser !== null): ?>
            <a href="/profile" class="dial-avatar" title="Meu perfil" aria-label="Meu perfil" data-nodrag>
                <?= Avatar::html($authUser['avatar_path'] ?? null, $authUser['name'], size: 'h-full w-full', radius: 'rounded-full', textSize: 'text-5xl') ?>
            </a>

            <div class="dial-me" data-nodrag>
                <p class="truncate text-sm font-semibold text-text-primary"><?= View::e($authUser['name']) ?></p>
                <span class="mt-1 inline-flex items-center gap-1.5 rounded bg-cyan/15 px-1.5 py-px text-[9px] font-bold uppercase tracking-wider text-cyan">
                    <span aria-hidden="true" class="status-dot h-1.5 w-1.5 rounded-full bg-success"></span><?= View::e(Labels::role($authUser['role'])) ?>
                </span>
            </div>

            <?php foreach ($shortcuts as [$href, $label, $icon, $tone, $k, $arc, $badge]): ?>
                <a href="<?= View::e($href) ?>" class="dial-btn <?= $tone ?>" style="--k: <?= $k ?>; --arc: <?= $arc ?>" aria-label="<?= View::e($label) ?>" data-nodrag>
                    <?= Icon::nav($icon) ?>
                    <?php if ($badge > 0): ?><span class="nav-badge"><?= $badge > 99 ? '99+' : (int) $badge ?></span><?php endif; ?>
                    <span class="dial-btn-tip"><?= View::e($label) ?></span>
                </a>
            <?php endforeach; ?>
            <form method="post" action="/logout" class="contents">
                <?= Csrf::field() ?>
                <button type="submit" class="dial-btn nav-tone-danger" style="--k: 1; --arc: 0rem" aria-label="Sair" data-nodrag>
                    <?= Icon::nav('logout') ?>
                    <span class="dial-btn-tip">Sair</span>
                </button>
            </form>

            <div class="dial-clock" data-nodrag>
                <?php require __DIR__ . '/_clock.php'; ?>
            </div>
        <?php endif; ?>

        <button type="button" class="dial-close" data-dial-toggle aria-label="Fechar o menu" title="Fechar o menu" data-nodrag>
            <?= Icon::nav('close') ?>
        </button>

        <?php // Anel de navegação: o dial.js posiciona os itens no arco (`is-live`) e gira; sem JS é uma lista. ?>
        <nav class="dial-ring" data-ring aria-label="Principal"<?= $ringTour !== null ? ' data-tour="' . $ringTour . '"' : '' ?>>
            <?php foreach ($ring as $it): ?>
                <?php if (isset($it['sep'])): ?>
                    <span class="dial-sep" data-scope="<?= $it['scope'] ?>" aria-hidden="true">
                        <span class="dial-sep-icon"><?= Icon::nav($it['sepIcon'] ?? 'sites') ?></span>
                        <span class="truncate"><?= View::e($it['sep']) ?></span>
                    </span>
                    <?php continue; ?>
                <?php endif; ?>
                <a href="<?= View::e($it['href']) ?>"<?= $it['tour'] !== null ? ' data-tour="' . View::e($it['tour']) . '"' : '' ?><?= $it['active'] ? ' aria-current="page"' : '' ?>
                   class="dial-item <?= $toneFor($it['scope']) ?>" data-scope="<?= $it['scope'] ?>" data-group="<?= View::e($it['group']) ?>">
                    <span class="dial-icon <?= $it['letter'] !== null ? 'font-display text-[.7rem] font-bold' : '' ?>"><?= $it['letter'] !== null ? View::e($it['letter']) : Icon::nav($it['icon']) ?></span>
                    <span class="dial-text">
                        <span class="dial-label"><?= View::e($it['label']) ?></span>
                        <span class="dial-sub"><?= View::e($it['inactive'] ? 'Inativo · ' . $it['group'] : $it['group']) ?></span>
                    </span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</aside>
