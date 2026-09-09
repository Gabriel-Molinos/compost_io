<?php

declare(strict_types=1);

use App\Support\Icon;
use App\View;

/** @var list<array{0:string,1:string,2:string}> $globalNav — [href, label, ícone] */
/** @var callable(string):bool $isActive */
/** @var bool $hasSiteNav */
/** @var array<string,mixed>|null $site */
/** @var list<array{0:string,1:string,2:string,3:bool}>|null $tabs — [chave, label, href, visível] */
/** @var string|null $activeTab */
/** @var list<array{id:int,name:string}> $quickSites — atalho fora do contexto de um site */
/** @var int $quickSitesTotal */
/** @var int $unreadNotifications */
/** @var bool $showNotifications */
/** @var int|null $tourSiteId — site que o tutorial guiado abre a partir daqui (assets/js/tour.js) */

// Fundo do chip do ícone varia por item (pedido do responsável, 2026-09-08:
// "mal distribuída"/monótona com todo ícone igual) — paleta fixa dentro da
// identidade (cyan/success/warning/danger/info, todas em baixa opacidade,
// nunca cor "crua" fora da paleta), escolhida por hash do nome do ícone.
// Hash (não índice sequencial) pra cada ícone sempre cair na mesma cor em
// qualquer tela — estável entre navegações, só "quebra" visualmente entre
// itens diferentes, não pisca a cada request.
// Variável local, não const: este arquivo é require'd duas vezes por
// página (sidebar desktop + menu mobile em <details>) — const no nível
// do arquivo quebraria na segunda inclusão ("Cannot redeclare constant").
$iconChipPalette = ['bg-cyan/15 text-cyan', 'bg-success/15 text-success',
    'bg-warning/15 text-warning', 'bg-danger/15 text-danger', 'bg-info/15 text-info'];

$iconChip = static function (string $icon) use ($iconChipPalette): string {
    $hash = crc32($icon);

    return $iconChipPalette[$hash % count($iconChipPalette)];
};

$navLink = static function (string $href, string $label, string $icon, bool $active, int $badge = 0, ?string $tourId = null) use ($iconChip): void {
    // Ativo reforça "onde estou" com tinta de fundo + barra de acento à
    // esquerda (não só cor — a barra é uma pista de forma, R-UI-07 — e o
    // fundo cyan/10 sozinho era sutil demais pra ler rápido na sidebar).
    $tone = $active
        ? 'border-l-2 border-cyan bg-cyan/10 pl-[calc(0.75rem-2px)] text-cyan'
        : 'border-l-2 border-transparent pl-3 text-text-secondary hover:bg-surface-2 hover:text-text-primary';
    $badgeHtml = $badge > 0
        ? '<span class="ml-auto flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-danger px-1 text-[11px] font-bold text-[#050B0F]">'
            . ($badge > 99 ? '99+' : $badge) . '</span>'
        : '';
    // Chip some quando ativo — o item ativo já tem sua própria cor de estado
    // (cyan sólido); manter o chip por baixo brigava com esse acento.
    $chipClass = $active ? 'text-cyan' : 'rounded-md p-1 ' . $iconChip($icon);
    $tourAttr = $tourId !== null ? ' data-tour="' . View::e($tourId) . '"' : '';
    echo '<a href="' . View::e($href) . '"' . $tourAttr . ' class="flex items-center gap-2.5 rounded-r-md py-1.5 pr-3 text-sm font-medium transition-colors ' . $tone . '">'
        . '<span class="flex shrink-0 items-center justify-center ' . $chipClass . '">' . Icon::nav($icon) . '</span>'
        . '<span class="truncate">' . View::e($label) . '</span>' . $badgeHtml . '</a>';
};
?>
<nav aria-label="Principal" class="flex flex-col gap-1">
    <?php foreach ($globalNav as [$href, $label, $icon]): ?>
        <?php $navLink($href, $label, $icon, $isActive($href)); ?>
    <?php endforeach; ?>
    <?php if ($showNotifications): ?>
        <?php $navLink('/notifications', 'Notificações', 'bell', $isActive('/notifications'), $unreadNotifications); ?>
    <?php endif; ?>
</nav>

<?php if ($hasSiteNav): ?>
    <div class="mt-6 border-t border-border pt-4">
        <a href="/sites/<?= View::e($site['id']) ?>"
           class="block truncate px-3 pb-2 text-xs font-semibold uppercase tracking-wide text-text-muted hover:text-cyan">
            <?= View::e($site['name']) ?>
        </a>
        <nav aria-label="Seções do site" data-tour="site-tabs" class="flex flex-col gap-1">
            <?php foreach ($tabs as [$key, $label, $href, $visible]): ?>
                <?php if (!$visible) { continue; } ?>
                <?php $navLink($href, $label, $key, ($activeTab ?? '') === $key, tourId: 'tab-' . $key); ?>
            <?php endforeach; ?>
        </nav>
    </div>
<?php elseif ($quickSites !== []): ?>
    <div class="mt-6 border-t border-border pt-4" data-tour="quick-sites">
        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Meus sites</p>
        <nav aria-label="Meus sites" class="flex flex-col gap-1">
            <?php foreach ($quickSites as $s): ?>
                <a href="/sites/<?= View::e($s['id']) ?>"
                   <?= isset($tourSiteId) && (int) $s['id'] === $tourSiteId ? 'data-tour="quick-sites-target"' : '' ?>
                   class="truncate rounded-md px-3 py-1.5 text-sm text-text-secondary transition-colors hover:bg-surface-2 hover:text-text-primary">
                    <?= View::e($s['name']) ?>
                </a>
            <?php endforeach; ?>
            <?php if ($quickSitesTotal > count($quickSites)): ?>
                <a href="/sites" class="rounded-md px-3 py-1.5 text-sm text-cyan hover:text-cyan-bright">
                    Ver todos (<?= $quickSitesTotal ?>) →
                </a>
            <?php endif; ?>
        </nav>
    </div>
<?php endif; ?>
