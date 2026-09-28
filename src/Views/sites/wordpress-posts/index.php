<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string, mixed> $site */
/** @var bool $configured */
/** @var list<array<string, mixed>> $posts */
/** @var string|null $lastSyncedAt */
/** @var array{all:int, compost:int, external:int} $originCounts */
/** @var list<string> $authors */
/** @var string|null $origin */
/** @var string|null $author */
/** @var string $image */
/** @var string|null $search */
/** @var bool $filtersActive */
/** @var int $totalFiltered */
/** @var int $page */
/** @var int $totalPages */

$activeTab = 'wpposts';
require __DIR__ . '/../_tabs.php';

$base = '/sites/' . View::e($site['id']) . '/wordpress-posts';
// Preserva os filtros ativos ao trocar de página (mesmo padrão de sites/production/index.php).
$baseParams = ['origin' => $origin, 'author' => $author, 'image' => $image !== '' ? $image : null, 'q' => $search];
$pageUrl = static function (int $p) use ($base, $baseParams): string {
    $params = array_filter($baseParams + ['page' => $p > 1 ? $p : null], static fn ($v): bool => $v !== null && $v !== '');

    return $base . ($params !== [] ? '?' . http_build_query($params) : '');
};
$statusMap = [
    'publish' => ['Publicado', 'success'],
    'future'  => ['Agendado', 'cyan'],
    'draft'   => ['Rascunho', 'muted'],
    'pending' => ['Pendente', 'warning'],
    'private' => ['Privado', 'muted'],
];
// '__none__' abaixo tem que bater com WordPressPostMirrorService::AUTHOR_NONE — valor literal
// (não a constante) porque esta view não importa a classe só por causa disso.
?>
<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="flex items-start gap-3">
        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('wordpress') ?></span>
        <div>
            <h1 class="font-display text-2xl font-bold text-text-primary">Todos os posts</h1>
            <p class="mt-1 max-w-xl text-sm text-text-secondary">
                Tudo que existe no WordPress deste site — feito pelo COMPOST ou direto lá. Copie o link, veja
                publicado ou edite sem precisar entrar no wp-admin.
            </p>
        </div>
    </div>
    <?php if ($configured): ?>
        <div class="text-right">
            <form method="post" action="<?= $base ?>/sync">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-primary inline-flex items-center gap-2 px-4 py-2 text-sm">
                    <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('arrow') ?></span>
                    Sincronizar agora
                </button>
            </form>
            <p class="mt-1.5 text-[11px] text-text-muted">
                <?php if ($lastSyncedAt !== null): ?>
                    Sincronizado <?= View::e(date('d/m/Y \à\s H:i', strtotime($lastSyncedAt))) ?>
                <?php else: ?>
                    <span class="text-warning">Ainda não sincronizado</span>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<?php if (!$configured): ?>
    <section class="mt-6 rounded-xl border border-border bg-surface p-10 text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-cyan/10 text-cyan [&>svg]:h-6 [&>svg]:w-6"><?= Icon::nav('wordpress') ?></span>
        <p class="mt-4 text-sm text-text-secondary">Este site ainda não tem uma conexão WordPress configurada.</p>
        <a href="/sites/<?= View::e($site['id']) ?>/wordpress" class="btn btn-primary mt-4 inline-flex px-4 py-2 text-sm">Configurar conexão</a>
    </section>
<?php else: ?>
    <?php
    $originTabs = [
        [null, 'Todos', $originCounts['all']],
        ['compost', 'COMPOST', $originCounts['compost']],
        ['external', 'Direto no WordPress', $originCounts['external']],
    ];
    ?>
    <div data-results-top class="transition-opacity duration-150">
        <div class="mt-6 flex flex-wrap gap-1.5" role="tablist" aria-label="Filtrar por origem">
            <?php foreach ($originTabs as [$key, $label, $n]): ?>
                <?php
                $active = $origin === $key;
                $qs = $key !== null ? '?origin=' . $key : '';
                ?>
                <a href="<?= $base . $qs ?>" role="tab" aria-selected="<?= $active ? 'true' : 'false' ?>"
                   class="rounded-full border px-3.5 py-1.5 text-xs font-medium transition-all duration-150
                          focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan/60
                          <?= $active
                                ? 'border-cyan bg-cyan/15 text-cyan shadow-[0_0_0_1px_rgba(0,208,240,.15)]'
                                : 'border-border text-text-secondary hover:border-cyan/50 hover:bg-surface-2 hover:text-text-primary active:scale-95' ?>">
                    <?= View::e($label) ?> <span class="font-mono"><?= $n ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="get" action="<?= $base ?>" data-filter-form
          class="mt-4 flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface-2/40 p-3.5">
        <?php if ($origin !== null): ?><input type="hidden" name="origin" value="<?= View::e($origin) ?>"><?php endif; ?>

        <label class="min-w-[14rem] flex-1 text-sm">
            <span class="block font-medium text-text-secondary">Buscar por título ou resumo</span>
            <input type="search" name="q" value="<?= View::e($search ?? '') ?>" placeholder="Ex.: viagem econômica"
                   data-filter-search autocomplete="off"
                   class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-1.5 text-text-primary placeholder:text-text-muted focus:border-cyan focus:outline-none">
        </label>
        <label class="min-w-[12rem] text-sm">
            <span class="block font-medium text-text-secondary">Autor</span>
            <select name="author" data-filter-auto class="mt-1 w-full">
                <option value="">Todos</option>
                <option value="__none__" <?= $author === '__none__' ? 'selected' : '' ?>>Sem autor</option>
                <?php foreach ($authors as $a): ?>
                    <option value="<?= View::e($a) ?>" <?= $author === $a ? 'selected' : '' ?>><?= View::e($a) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="min-w-[11rem] text-sm">
            <span class="block font-medium text-text-secondary">Imagem destacada</span>
            <select name="image" data-filter-auto class="mt-1 w-full">
                <option value="">Todas</option>
                <option value="with" <?= $image === 'with' ? 'selected' : '' ?>>Com imagem</option>
                <option value="none" <?= $image === 'none' ? 'selected' : '' ?>>Sem imagem</option>
            </select>
        </label>
        <button type="submit" class="btn btn-secondary px-3.5 py-1.5 text-sm">Filtrar</button>
        <?php // Sempre no DOM (só `hidden` quando não há filtro) pro script poder mostrar/esconder sem recarregar. ?>
        <a href="<?= $base ?>" data-clear-filters <?= $filtersActive ? '' : 'hidden' ?>
           class="rounded-md px-2 py-1.5 text-sm text-text-muted transition-colors hover:text-text-primary">
            Limpar filtros
        </a>
    </form>

    <div data-results-body class="transition-opacity duration-150">
        <?php if ($posts === []): ?>
            <section class="mt-6 rounded-xl border border-border bg-surface p-10 text-center">
                <p class="text-sm text-text-secondary">
                    <?= $filtersActive ? 'Nenhum post neste filtro.' : 'Nenhum post sincronizado ainda — clique em "Sincronizar agora".' ?>
                </p>
            </section>
        <?php else: ?>
            <p class="mt-4 text-xs text-text-muted">
                <?= $totalFiltered ?> post(s) neste filtro<?= $totalPages > 1 ? ' — página ' . $page . ' de ' . $totalPages : '' ?>.
            </p>
            <ul class="mt-3 space-y-3">
                <?php foreach ($posts as $p): ?>
                    <?php
                    [$statusLabel, $statusTone] = $statusMap[$p['status']] ?? [$p['status'], 'muted'];
                    $isCompost = $p['article_id'] !== null;
                    ?>
                    <li class="hover-card flex flex-wrap items-center gap-4 rounded-xl border border-border bg-surface p-4 sm:flex-nowrap">
                        <?php if (!empty($p['featured_image_url'])): ?>
                            <img src="<?= View::e($p['featured_image_url']) ?>" alt="" loading="lazy" class="h-16 w-16 shrink-0 rounded-lg object-cover">
                        <?php else: ?>
                            <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border border-dashed border-border text-text-muted [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('camera') ?></span>
                        <?php endif; ?>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <a href="<?= $base ?>/<?= View::e($p['id']) ?>/edit"
                                   class="truncate font-display text-sm font-semibold text-text-primary hover:text-cyan" title="<?= View::e($p['title']) ?>">
                                    <?= View::e($p['title']) ?>
                                </a>
                            </div>
                            <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold <?= Labels::toneClasses($statusTone) ?>"><?= View::e($statusLabel) ?></span>
                                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium <?= $isCompost ? 'border-cyan/40 text-cyan' : 'border-border text-text-muted' ?>">
                                    <?= $isCompost ? 'COMPOST' : 'Direto no WordPress' ?>
                                </span>
                                <?php if (empty($p['featured_image_url'])): ?>
                                    <span class="inline-flex items-center gap-1 rounded-full border border-warning/40 px-2 py-0.5 text-[11px] font-medium text-warning">Sem imagem</span>
                                <?php endif; ?>
                                <span class="flex items-center gap-3 text-xs text-text-muted">
                                    <?php if (!empty($p['wordpress_author_name'])): ?>
                                        <span class="inline-flex items-center gap-1"><span class="[&>svg]:h-3 [&>svg]:w-3"><?= Icon::nav('users') ?></span><?= View::e($p['wordpress_author_name']) ?></span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-warning/80"><span class="[&>svg]:h-3 [&>svg]:w-3"><?= Icon::nav('users') ?></span>Sem autor</span>
                                    <?php endif; ?>
                                    <?php if (!empty($p['wordpress_category_names'])): ?>
                                        <span class="inline-flex items-center gap-1"><span class="[&>svg]:h-3 [&>svg]:w-3"><?= Icon::nav('categories') ?></span><?= View::e($p['wordpress_category_names']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($p['wordpress_published_at'])): ?>
                                        <span class="inline-flex items-center gap-1"><span class="[&>svg]:h-3 [&>svg]:w-3"><?= Icon::nav('calendar') ?></span><?= View::e(date('d/m/Y', strtotime((string) $p['wordpress_published_at']))) ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <button type="button" data-copy="<?= View::e($p['link']) ?>" title="Copiar link" aria-label="Copiar link"
                                    class="flex h-9 w-9 items-center justify-center rounded-md border border-border text-text-secondary transition-colors hover:border-cyan hover:text-cyan [&>svg]:h-4 [&>svg]:w-4">
                                <?= Icon::nav('links') ?>
                            </button>
                            <a href="<?= View::e($p['link']) ?>" target="_blank" rel="noopener" title="Ver publicado" aria-label="Ver publicado"
                               class="flex h-9 w-9 items-center justify-center rounded-md border border-border text-text-secondary transition-colors hover:border-cyan hover:text-cyan [&>svg]:h-4 [&>svg]:w-4">
                                <?= Icon::nav('globe') ?>
                            </a>
                            <a href="<?= $base ?>/<?= View::e($p['id']) ?>/edit" class="btn btn-primary px-3 py-1.5 text-xs">Editar</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($totalPages > 1): ?>
                <?php $pagerBtn = 'btn btn-secondary px-3 py-1.5 text-sm'; ?>
                <nav class="mt-5 flex items-center justify-center gap-3 text-sm" aria-label="Paginação">
                    <?php if ($page > 1): ?>
                        <a href="<?= View::e($pageUrl($page - 1)) ?>" class="<?= $pagerBtn ?>">← Anterior</a>
                    <?php else: ?>
                        <span class="<?= $pagerBtn ?>" aria-disabled="true">← Anterior</span>
                    <?php endif; ?>
                    <span class="text-text-secondary">Página <?= $page ?> de <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= View::e($pageUrl($page + 1)) ?>" class="<?= $pagerBtn ?>">Próxima →</a>
                    <?php else: ?>
                        <span class="<?= $pagerBtn ?>" aria-disabled="true">Próxima →</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script src="<?= View::e(View::asset('assets/js/live-filter.js')) ?>" defer></script>
<script>
    // Progressive enhancement: copiar o link sem sair da página (mesmo padrão de sites/sources/index.php).
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy]');
        if (!btn) return;
        var text = btn.getAttribute('data-copy') || '';
        var original = btn.innerHTML;
        var done = function () {
            btn.classList.add('border-cyan', 'text-cyan');
            setTimeout(function () { btn.classList.remove('border-cyan', 'text-cyan'); }, 1200);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(done).catch(function () {});
        }
    });
</script>
