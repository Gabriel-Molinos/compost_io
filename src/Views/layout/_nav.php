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

$navLink = static function (string $href, string $label, string $icon, bool $active, int $badge = 0): void {
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
    echo '<a href="' . View::e($href) . '" class="flex items-center gap-2.5 rounded-r-md py-2 pr-3 text-sm font-medium transition-colors ' . $tone . '">'
        . Icon::nav($icon) . '<span class="truncate">' . View::e($label) . '</span>' . $badgeHtml . '</a>';
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
        <nav aria-label="Seções do site" class="flex flex-col gap-1">
            <?php foreach ($tabs as [$key, $label, $href, $visible]): ?>
                <?php if (!$visible) { continue; } ?>
                <?php $navLink($href, $label, $key, ($activeTab ?? '') === $key); ?>
            <?php endforeach; ?>
        </nav>
    </div>
<?php elseif ($quickSites !== []): ?>
    <div class="mt-6 border-t border-border pt-4">
        <p class="px-3 pb-2 text-xs font-semibold uppercase tracking-wide text-text-muted">Meus sites</p>
        <nav aria-label="Meus sites" class="flex flex-col gap-1">
            <?php foreach ($quickSites as $s): ?>
                <a href="/sites/<?= View::e($s['id']) ?>"
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
