<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Flag;
use App\Support\Icon;
use App\Support\Labels;
use App\Support\Languages;
use App\Support\SiteListing;
use App\View;

/** @var list<array<string, mixed>> $sites Já filtrados e ordenados */
/** @var bool $isAdmin */
/** @var array<string, int> $counts all/active/inactive/attention e ok/issue/none — da lista completa */
/** @var array<string, array{label: string, count: int}> $languages idiomas presentes, por chave de filtro */
/** @var array{q: string, status: string, wp: string, lang: string, order: string} $filters */
/** @var bool $filtersActive */

$presets = Languages::presets();

// Tags do canto de cima da borda (tom próprio de cada uma — os nomes ficam literais aqui pro scanner do Tailwind).
$wpTags = [
    'OK'         => ['WordPress conectado', 'check', 'article-card-tag--success'],
    'FAILED'     => ['Falha no WordPress', 'alert', 'article-card-tag--danger'],
    'UNVERIFIED' => ['WP não verificado', 'clock', 'article-card-tag--warning'],
    'NONE'       => ['Sem WordPress', 'wordpress', 'article-card-tag--muted'],
];

$statusChips = [
    ['', 'Todos', $counts['all'], ''],
    ['active', 'Ativos', $counts['active'], 'pick-green'],
    ['inactive', 'Inativos', $counts['inactive'], ''],
    ['attention', 'Com atenção', $counts['attention'], 'pick-danger'],
];
$wpChips = [
    ['', 'Todos', $counts['all'], ''],
    ['ok', 'Conectado', $counts['ok'], 'pick-green'],
    ['issue', 'Com problema', $counts['issue'], 'pick-danger'],
    ['none', 'Sem conexão', $counts['none'], ''],
];
$orders = [
    'name'      => 'Nome (A–Z)',
    'attention' => 'Precisam de atenção primeiro',
    'review'    => 'Mais em revisão',
    'newest'    => 'Mais recentes',
];
$shown = count($sites);
?>
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="font-display text-2xl font-bold text-text-primary">Sites</h1>
        <p class="mt-1 text-sm text-text-secondary">
            <?= $counts['all'] ?> <?= $counts['all'] === 1 ? 'site' : 'sites' ?>
            <?php if ($counts['attention'] > 0): ?>
                · <span class="font-semibold text-danger"><?= $counts['attention'] ?> pedindo atenção</span>
            <?php endif; ?>
        </p>
    </div>
    <?php if ($isAdmin): ?>
        <a href="/sites/new" class="btn btn-primary px-4 py-2 text-sm">
            <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('plus') ?></span>
            Novo site
        </a>
    <?php endif; ?>
</div>

<?php if ($counts['all'] === 0): ?>
    <p class="mt-8 text-sm text-text-secondary">
        <?= $isAdmin ? 'Nenhum site cadastrado ainda.' : 'Você não está vinculado a nenhum site.' ?>
    </p>
<?php else: ?>
    <?php // Filtros: todos "de verdade" (GET /sites?…) — o formulário nunca é trocado pelo filtro em tempo real (script no fim), só as regiões de resultado; assim foco e cursor da busca ficam quietos. ?>
    <form method="get" action="/sites" data-filter-form class="mt-6 rounded-2xl border border-border bg-surface p-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative min-w-[14rem] flex-1">
                <span aria-hidden="true" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-cyan [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('search') ?></span>
                <label for="f_sites_q" class="sr-only">Buscar sites</label>
                <input type="search" id="f_sites_q" name="q" value="<?= View::e($filters['q']) ?>" data-filter-search autocomplete="off"
                       placeholder="Buscar por nome, nicho, idioma ou endereço…"
                       class="w-full rounded-xl border border-border bg-surface-2 py-2.5 pl-10 pr-4 text-text-primary placeholder:text-text-muted focus:border-cyan focus:outline-none">
            </div>
            <?php if (count($languages) > 1): ?>
                <label class="w-44 text-sm">
                    <span class="sr-only">Idioma</span>
                    <select name="lang" data-filter-auto class="w-full">
                        <option value="">Todos os idiomas</option>
                        <?php foreach ($languages as $key => $l): ?>
                            <option value="<?= View::e($key) ?>" <?= $filters['lang'] === $key ? 'selected' : '' ?>><?= View::e($l['label']) ?> (<?= $l['count'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label class="w-56 text-sm">
                <span class="sr-only">Ordenar por</span>
                <select name="order" data-filter-auto class="w-full">
                    <?php foreach ($orders as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $filters['order'] === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-3">
            <div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-label="Situação">
                <span class="mr-1 text-[11px] font-bold uppercase tracking-widest text-text-muted">Situação</span>
                <?php foreach ($statusChips as [$value, $label, $n, $tone]): ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="status" value="<?= $value ?>" data-filter-auto class="peer sr-only" <?= $filters['status'] === $value ? 'checked' : '' ?>>
                        <span class="pick pick-chip <?= $tone ?>"><?= View::e($label) ?> <span class="font-mono"><?= $n ?></span></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-label="WordPress">
                <span class="mr-1 text-[11px] font-bold uppercase tracking-widest text-text-muted">WordPress</span>
                <?php foreach ($wpChips as [$value, $label, $n, $tone]): ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="wp" value="<?= $value ?>" data-filter-auto class="peer sr-only" <?= $filters['wp'] === $value ? 'checked' : '' ?>>
                        <span class="pick pick-chip <?= $tone ?>"><?= View::e($label) ?> <span class="font-mono"><?= $n ?></span></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php // Sempre no DOM (só `hidden` sem filtro) pro script mostrar/esconder sem recarregar. ?>
            <a href="/sites" data-clear-filters <?= $filtersActive ? '' : 'hidden' ?>
               class="ml-auto rounded-md px-2 py-1.5 text-sm text-text-muted transition-colors hover:text-text-primary">
                Limpar filtros
            </a>
        </div>
    </form>

    <div data-results-top class="mt-6 transition-opacity duration-150">
        <p class="text-sm text-text-secondary" aria-live="polite">
            <?php if ($shown === $counts['all']): ?>
                Mostrando <strong class="text-text-primary">todos os <?= $shown ?></strong>
            <?php else: ?>
                <strong class="text-text-primary"><?= $shown ?></strong> de <?= $counts['all'] ?> sites
            <?php endif; ?>
        </p>
    </div>

    <div data-results-body class="transition-opacity duration-150">
        <?php if ($sites === []): ?>
            <div class="mt-4 rounded-2xl border border-dashed border-border-strong p-10 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-cyan/10 text-cyan [&>svg]:h-6 [&>svg]:w-6"><?= Icon::nav('search') ?></span>
                <p class="mt-3 font-display text-base font-semibold text-text-primary">Nenhum site encontrado</p>
                <p class="mt-1 text-sm text-text-secondary">Nenhum site bate com esses filtros. Tente outro termo ou limpe os filtros.</p>
            </div>
        <?php else: ?>
            <ul class="mt-6 space-y-9">
                <?php foreach ($sites as $i => $site): ?>
                    <?php
                    $active = (int) $site['is_active'] === 1;
                    $tone = SiteListing::tone($site);
                    $wpState = SiteListing::wpState($site);
                    [$wpLabel, $wpIcon, $wpTagTone] = $wpTags[$wpState];
                    $cardStyle = sprintf('--card-enter: %dms; --card-phase: -%.2Fs', min($i, 8) * 35, ($i % 7) * 0.85); // %F: sem vírgula de locale no CSS
                    $langKey = Languages::presetFor((string) $site['language']);
                    $host = $site['wordpress_url'] ? (string) preg_replace('~^https?://|/$~', '', (string) $site['wordpress_url']) : '';
                    $stats = [
                        ['Em revisão', $site['in_review'], 'text-cyan'],
                        ['Aprovados', $site['done'], 'text-success'],
                        ['Com problema', $site['attention'], 'text-danger'],
                    ];
                    ?>
                    <li class="article-card hover-card <?= Labels::articleCardTone($tone) ?> rounded-2xl" style="<?= $cardStyle ?>">
                        <span class="article-card-fx" aria-hidden="true"></span>

                        <span class="article-card-tags">
                            <span class="article-card-tag <?= $active ? 'article-card-tag--success' : 'article-card-tag--muted' ?>">
                                <?php if ($active): ?><span aria-hidden="true" class="status-dot h-1.5 w-1.5 rounded-full bg-current"></span><?php endif; ?>
                                <?= $active ? 'Ativo' : 'Inativo' ?>
                            </span>
                            <span class="article-card-tag <?= $wpTagTone ?>">
                                <?= Icon::nav($wpIcon) ?>
                                <?= View::e($wpLabel) ?>
                            </span>
                        </span>

                        <div class="flex flex-col gap-5 p-4 pt-6 md:flex-row md:p-5 md:pt-6">
                            <?php // Cabeçalho da logo: placa branca (a logo respira dentro dela e cresce um pouco no hover do card). ?>
                            <div class="flex h-36 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-4 shadow-[0_10px_30px_-14px_rgba(0,0,0,.8)] md:h-auto md:min-h-[9.5rem] md:w-64">
                                <span class="block w-full transition-transform duration-500 ease-out [.hover-card:hover_&]:scale-105">
                                    <?= Avatar::html($site['logo_path'] ?? null, (string) $site['name'], size: 'h-24 w-full', radius: 'rounded-lg', textSize: 'text-4xl', fit: 'contain', bg: 'bg-white') ?>
                                </span>
                            </div>

                            <div class="flex min-w-0 flex-1 flex-col gap-3">
                                <h2 class="min-w-0">
                                    <a href="/sites/<?= View::e($site['id']) ?>"
                                       class="article-card-title block truncate font-display text-2xl font-bold after:absolute after:inset-0 after:z-[1] after:rounded-2xl after:content-['']">
                                        <?= View::e($site['name']) ?>
                                    </a>
                                </h2>

                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                                    <?php if (($site['niche'] ?? '') !== ''): ?>
                                        <span class="article-card-fact article-card-fact--chip">
                                            <span class="article-card-icon"><?= Icon::nav('categories') ?></span>
                                            <?= View::e($site['niche']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="article-card-fact">
                                        <?php if ($langKey !== null): ?>
                                            <span class="block h-4 w-6 overflow-hidden rounded-[3px] shadow-[0_0_0_1px_rgba(255,255,255,.2)]"><?= Flag::svg($presets[$langKey]['flag']) ?></span>
                                        <?php else: ?>
                                            <span class="article-card-icon"><?= Icon::nav('globe') ?></span>
                                        <?php endif; ?>
                                        <?= View::e($langKey !== null ? $presets[$langKey]['label'] : (string) $site['language']) ?>
                                    </span>
                                    <?php if ($host !== ''): ?>
                                        <span class="inline-flex min-w-0 items-center gap-1.5 text-sm text-text-secondary">
                                            <span class="article-card-icon"><?= Icon::nav('wordpress') ?></span>
                                            <span class="truncate font-mono text-xs"><?= View::e($host) ?></span>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-auto flex flex-wrap items-end justify-between gap-4 pt-1">
                                    <dl class="flex gap-2.5">
                                        <?php foreach ($stats as [$label, $n, $color]): ?>
                                            <div class="min-w-[5.25rem] rounded-lg border border-white/10 bg-white/5 px-3 py-2">
                                                <dt class="text-[10px] font-bold uppercase tracking-wider text-text-muted"><?= $label ?></dt>
                                                <dd class="mt-0.5 font-display text-xl font-bold <?= $n > 0 ? $color : 'text-text-muted' ?>"><?= $n > 0 ? (int) $n : '—' ?></dd>
                                            </div>
                                        <?php endforeach; ?>
                                    </dl>
                                    <a href="/sites/<?= View::e($site['id']) ?>" aria-label="Abrir <?= View::e($site['name']) ?>"
                                       class="btn btn-primary group/open relative z-10 px-6 py-3 text-sm">
                                        Abrir site
                                        <span class="transition-transform duration-200 group-hover/open:translate-x-1 [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('arrow') ?></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <script>
        // Filtro em tempo real — mesma técnica da aba Produção: escolhas discretas
        // (chips, selects) atualizam na hora; a busca espera uma pausa de digitação.
        // Busca a MESMA URL (?q=…&status=…) via fetch e troca só as duas regiões de
        // resultado; o formulário nunca é trocado (foco e cursor ficam). Qualquer
        // falha cai numa navegação normal pra mesma URL.
        (function () {
            var form = document.querySelector('[data-filter-form]');
            var top = document.querySelector('[data-results-top]');
            var body = document.querySelector('[data-results-body]');
            if (!form || !top || !body) return;

            var clear = form.querySelector('[data-clear-filters]');
            var search = form.querySelector('[data-filter-search]');
            var timer = null;
            var inflight = null;

            function filterUrl() {
                var params = new URLSearchParams();
                new FormData(form).forEach(function (value, key) {
                    if (value !== '' && !(key === 'order' && value === 'name')) params.append(key, value);
                });
                var qs = params.toString();
                return form.getAttribute('action') + (qs ? '?' + qs : '');
            }

            function setBusy(busy) {
                [top, body].forEach(function (el) {
                    el.classList.toggle('opacity-50', busy);
                    el.setAttribute('aria-busy', busy ? 'true' : 'false');
                });
            }

            function update() {
                if (timer) { clearTimeout(timer); timer = null; }
                var url = filterUrl();
                if (inflight) inflight.abort();
                var mine = inflight = new AbortController();
                setBusy(true);

                fetch(url, { credentials: 'same-origin', signal: mine.signal })
                    .then(function (res) {
                        if (!res.ok) throw new Error('HTTP ' + res.status);
                        return res.text();
                    })
                    .then(function (html) {
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        var newTop = doc.querySelector('[data-results-top]');
                        var newBody = doc.querySelector('[data-results-body]');
                        if (!newTop || !newBody) throw new Error('resposta sem as regiões de resultado');
                        top.innerHTML = newTop.innerHTML;
                        body.innerHTML = newBody.innerHTML;
                        if (clear) {
                            var newClear = doc.querySelector('[data-clear-filters]');
                            clear.hidden = !newClear || newClear.hidden;
                        }
                        history.replaceState(null, '', url);
                        setBusy(false);
                    })
                    .catch(function (err) {
                        if (err.name === 'AbortError') return; // trocada por uma consulta mais nova
                        window.location.href = url;
                    })
                    .finally(function () {
                        if (inflight === mine) inflight = null;
                    });
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                update();
            });
            form.querySelectorAll('[data-filter-auto]').forEach(function (field) {
                field.addEventListener('change', update);
            });
            if (search) {
                search.addEventListener('input', function () {
                    if (timer) clearTimeout(timer);
                    timer = setTimeout(update, 350);
                });
            }
        })();
    </script>
<?php endif; ?>
