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

$navLink = static function (string $href, string $label, string $icon, bool $active): void {
    $tone = $active
        ? 'bg-cyan/10 text-cyan'
        : 'text-text-secondary hover:bg-surface-2 hover:text-text-primary';
    echo '<a href="' . View::e($href) . '" class="flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors ' . $tone . '">'
        . Icon::nav($icon) . '<span class="truncate">' . View::e($label) . '</span></a>';
};
?>
<nav aria-label="Principal" class="flex flex-col gap-1">
    <?php foreach ($globalNav as [$href, $label, $icon]): ?>
        <?php $navLink($href, $label, $icon, $isActive($href)); ?>
    <?php endforeach; ?>
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
<?php endif; ?>
