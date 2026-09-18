<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string,mixed> $site */
/** @var list<array<string,mixed>> $articles */
/** @var list<array<string,mixed>> $goals */
/** @var list<array<string,mixed>> $categories */
/** @var array{all: int, done: int, attention: int, progress: int, discarded: int} $counts */
/** @var array<string, int> $exactCounts — status exato => contagem, dentro do grupo atual (vazio se o grupo só tem 1 status) */
/** @var string $statusGroup */
/** @var string|null $exactStatus */
/** @var int|null $categoryId */
/** @var string|null $origin */
/** @var string|null $search */
/** @var bool $filtersActive */
/** @var int $totalForGroup */
/** @var int $page */
/** @var int $totalPages */
/** @var array{id: int}|null $tourReviewArticle */

$activeTab = 'production';
require __DIR__ . '/../_tabs.php';

// Filtro melhor na Produção (pedido do responsável, 2026-09-17): além das
// abas de grupo (desde 2026-09-15, ?status=...&page=...), agora dá pra
// refinar por status exato, categoria, origem e busca — tudo combinável,
// sempre navegação de verdade (querystring), nunca JS escondendo linha —
// com a lista paginada, filtrar por cima só da página atual dava contagem
// errada (achado real da versão anterior deste filtro).
$baseParams = [
    'status'       => $statusGroup !== 'all' ? $statusGroup : null,
    'exact_status' => $exactStatus,
    'category_id'  => $categoryId,
    'origin'       => $origin,
    'q'            => $search,
];
$buildUrl = static function (array $overrides) use ($site, $baseParams): string {
    $params = array_filter(array_merge($baseParams, $overrides), static fn ($v): bool => $v !== null && $v !== '');

    return '/sites/' . $site['id'] . '/production' . ($params !== [] ? '?' . http_build_query($params) : '');
};
// Trocar de grupo limpa o sub-filtro de status exato (pertence ao grupo
// anterior) e some da paginação, mas preserva categoria/origem/busca.
$statusUrl = static fn (string $group): string => $buildUrl(['status' => $group === 'all' ? null : $group, 'exact_status' => null, 'page' => null]);
$exactUrl  = static fn (?string $status): string => $buildUrl(['exact_status' => $status, 'page' => null]);
$pageUrl   = static fn (int $p): string => $buildUrl(['page' => $p]);

$deleteIcon = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" '
    . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16"/>'
    . '<path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13"/>'
    . '<path d="M10 11v6"/><path d="M14 11v6"/></svg>';
?>
<h2 class="font-display text-lg font-semibold text-text-primary">Produção</h2>
<p class="mt-1 text-sm text-text-secondary">
    Todo dia, 1 rascunho novo é gerado automaticamente por este site (se estiver ativo) —
    marcado como <span class="text-cyan">automático</span> na lista abaixo. Cada geração faz
    várias chamadas ao Gemini e <strong>tem custo</strong>. Use o formulário abaixo pra gerar
    um extra quando quiser.
</p>

<section data-tour="generate-form" class="mt-5 overflow-hidden rounded-lg border border-border bg-surface">
    <div class="border-b border-border bg-surface-2/40 px-5 py-3">
        <h3 class="font-display text-base font-semibold text-text-primary">Gerar novo rascunho</h3>
    </div>
    <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/generate"
          class="flex flex-wrap items-end gap-3 p-5"
          onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Gerando… (pode levar 1–2 min)';">
        <?= Csrf::field() ?>
        <label class="min-w-[11rem] text-sm">
            <span class="block font-medium text-text-secondary">Meta</span>
            <select name="goal_id" class="mt-1 w-full">
                <option value="">— (sem meta)</option>
                <?php foreach ($goals as $g): ?>
                    <option value="<?= View::e($g['id']) ?>"><?= View::e($g['period']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="min-w-[11rem] text-sm">
            <span class="block font-medium text-text-secondary">Categoria</span>
            <select name="category_id" class="mt-1 w-full">
                <option value="">— (a IA escolhe)</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= View::e($c['id']) ?>"><?= View::e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
            Gerar rascunho
        </button>
    </form>
</section>

<?php if ($counts['all'] === 0 && !$filtersActive): ?>
    <p class="mt-6 text-text-secondary">Nenhum artigo produzido ainda.</p>
<?php else: ?>
    <?php
    $filterTabs = [
        ['all', 'Todos', $counts['all']],
        ['progress', 'Em andamento', $counts['progress']],
        ['done', 'Aprovados', $counts['done']],
        ['attention', 'Atenção', $counts['attention']],
        ['discarded', 'Descartados', $counts['discarded']],
    ];
    ?>
    <?php // [data-results-top] e [data-results-body] são as duas regiões que o filtro em tempo real troca (script no fim); o formulário entre elas nunca é trocado, senão foco e cursor da busca se perderiam. ?>
    <div data-results-top class="transition-opacity duration-150">
    <div class="mt-8 flex items-center justify-between gap-3">
        <h3 class="font-display text-lg font-semibold text-text-primary">Rascunhos (<?= $totalForGroup ?>)</h3>
        <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Filtrar por status">
            <?php foreach ($filterTabs as [$key, $label, $n]): ?>
                <?php if ($key !== 'all' && $n === 0) continue; ?>
                <?php $active = $key === $statusGroup; ?>
                <a href="<?= View::e($statusUrl($key)) ?>" role="tab" aria-selected="<?= $active ? 'true' : 'false' ?>"
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

    <?php if ($exactCounts !== []): ?>
        <?php // Sub-filtro por status exato dentro do grupo atual (ex.: "Aprovados" = Aprovado + Agendado + Publicado) — só aparece quando o grupo tem mais de 1 status real. ?>
        <div class="mt-2.5 flex flex-wrap items-center gap-1.5 pl-1" role="tablist" aria-label="Refinar por status exato">
            <span class="text-xs text-text-muted">Refinar:</span>
            <a href="<?= View::e($exactUrl(null)) ?>" role="tab" aria-selected="<?= $exactStatus === null ? 'true' : 'false' ?>"
               class="rounded-full border px-2.5 py-1 text-[11px] font-medium transition-all duration-150
                      focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan/60
                      <?= $exactStatus === null
                            ? 'border-cyan bg-cyan/15 text-cyan'
                            : 'border-border text-text-secondary hover:border-cyan/50 hover:bg-surface-2 hover:text-text-primary active:scale-95' ?>">
                Todos
            </a>
            <?php foreach ($exactCounts as $exactKey => $n): ?>
                <?php if ($n === 0) continue; ?>
                <?php $active = $exactStatus === $exactKey; ?>
                <a href="<?= View::e($exactUrl($exactKey)) ?>" role="tab" aria-selected="<?= $active ? 'true' : 'false' ?>"
                   class="rounded-full border px-2.5 py-1 text-[11px] font-medium transition-all duration-150
                          focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-cyan/60
                          <?= $active
                                ? 'border-cyan bg-cyan/15 text-cyan'
                                : 'border-border text-text-secondary hover:border-cyan/50 hover:bg-surface-2 hover:text-text-primary active:scale-95' ?>">
                    <?= View::e(Labels::articleStatus($exactKey)) ?> <span class="font-mono"><?= $n ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </div>

    <form method="get" action="/sites/<?= View::e($site['id']) ?>/production"
          data-filter-form
          class="mt-4 flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface-2/40 p-3.5">
        <?php if ($statusGroup !== 'all'): ?><input type="hidden" name="status" value="<?= View::e($statusGroup) ?>"><?php endif; ?>
        <?php if ($exactStatus !== null): ?><input type="hidden" name="exact_status" value="<?= View::e($exactStatus) ?>"><?php endif; ?>

        <label class="min-w-[14rem] text-sm">
            <span class="block font-medium text-text-secondary">Categoria</span>
            <select name="category_id" data-filter-auto class="mt-1 w-full">
                <option value="">Todas</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= View::e($c['id']) ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= View::e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="min-w-[9rem] text-sm">
            <span class="block font-medium text-text-secondary">Origem</span>
            <select name="origin" data-filter-auto class="mt-1 w-full">
                <option value="">Todas</option>
                <option value="AUTO" <?= $origin === 'AUTO' ? 'selected' : '' ?>>Automático</option>
                <option value="MANUAL" <?= $origin === 'MANUAL' ? 'selected' : '' ?>>Manual</option>
            </select>
        </label>
        <label class="min-w-[14rem] flex-1 text-sm">
            <span class="block font-medium text-text-secondary">Buscar por título ou palavra-chave</span>
            <input type="search" name="q" value="<?= View::e($search ?? '') ?>" placeholder="Ex.: marketing digital"
                   data-filter-search autocomplete="off"
                   class="mt-1 w-full rounded-md border border-border bg-surface px-3 py-1.5 text-text-primary placeholder:text-text-muted focus:border-cyan focus:outline-none">
        </label>
        <button type="submit"
                class="btn btn-secondary px-3.5 py-1.5 text-sm">
            Filtrar
        </button>
        <?php // Sempre no DOM (só `hidden` quando não há filtro) pro script poder mostrar/esconder sem recarregar a página. ?>
        <a href="/sites/<?= View::e($site['id']) ?>/production" data-clear-filters <?= $filtersActive ? '' : 'hidden' ?>
           class="rounded-md px-2 py-1.5 text-sm text-text-muted transition-colors hover:text-text-primary">
            Limpar filtros
        </a>
    </form>

    <?php
    // Tutorial guiado (assets/js/tour.js): quando existe um rascunho em
    // revisão, o passo "Revisar um rascunho" pode navegar direto pra ele e
    // mostrar Aprovar/Rejeitar de verdade em vez de só descrever em texto.
    // Vem do controller (`ArticleService::firstInReview()`) desde a
    // paginação — o rascunho em revisão pode estar em qualquer página.
    $tourReviewHref = $tourReviewArticle !== null
        ? '/sites/' . $site['id'] . '/production/' . $tourReviewArticle['id']
        : '';
    ?>
    <div data-results-body class="transition-opacity duration-150">
    <?php if ($articles === []): ?>
        <p class="mt-3 text-sm text-text-secondary">Nenhum rascunho neste filtro.</p>
    <?php endif; ?>
    <ul data-tour="article-list" data-tour-review-href="<?= View::e($tourReviewHref) ?>" class="mt-6 space-y-6">
        <?php // Espaço maior entre os cards: a tag da situação fica meio pra fora da borda de cima e precisa de ar. ?>
        <?php foreach ($articles as $i => $a): ?>
            <?php
            $tone = Labels::articleStatusTone((string) $a['status']);
            $isGenerating = in_array($a['status'], ['PLANNED', 'IN_PROGRESS'], true);
            // Fundo e animação por estado (.article-card--* em input.css). Entrada
            // escalonada só nos primeiros cards; fase negativa por posição pra
            // os brilhos/respirações não baterem todos no mesmo instante.
            $cardStyle = sprintf('--card-enter: %dms; --card-phase: -%.2Fs', min($i, 8) * 35, ($i % 7) * 0.85); // %F: sem vírgula de locale no CSS
            // Detalhes de menor peso (o que importa — data e categoria — sobe pra
            // linha de destaque abaixo). Já escapados; vão juntos, separados por ·.
            $details = [];
            if ((int) ($a['attempt_number'] ?? 1) > 1) {
                $details[] = 'tentativa ' . View::e($a['attempt_number']);
            }
            if (!empty($a['word_count'])) {
                $details[] = View::e($a['word_count']) . ' palavras';
            }
            $details[] = '<span class="font-mono">US$ ' . number_format((float) $a['ai_cost'], 4) . '</span>';
            ?>
            <li class="article-card <?= Labels::articleCardTone($tone) ?> flex flex-col gap-2.5 rounded-lg px-4 pb-3.5 pt-5"
                style="<?= View::e($cardStyle) ?>">
                <span class="article-card-fx" aria-hidden="true"></span>
                <?php // Tag da situação (cor do estado, sobre a borda de cima) — texto + cor, nunca só cor (R-UI-07). ?>
                <span class="article-card-tag">
                    <?php if ($isGenerating): ?><span class="status-dot h-1.5 w-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span><?php endif; ?>
                    <?= View::e(Labels::articleStatus((string) $a['status'])) ?>
                </span>
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <a href="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($a['id']) ?>"
                           class="article-card-title font-semibold">
                            <?= View::e($a['title'] ?: 'Rascunho #' . $a['id']) ?>
                        </a>
                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-2">
                            <span class="article-card-fact">
                                <span class="article-card-icon"><?= Icon::nav('calendar') ?></span>
                                <?= View::e(date('d/m/Y', strtotime((string) $a['created_at']))) ?>
                            </span>
                            <?php if (!empty($a['category_name'])): ?>
                                <span class="article-card-fact article-card-fact--chip">
                                    <span class="article-card-icon"><?= Icon::nav('categories') ?></span>
                                    <?= View::e($a['category_name']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (($a['source'] ?? 'MANUAL') === 'AUTO'): ?>
                                <span class="article-card-pill">automático</span>
                            <?php endif; ?>
                            <span class="text-xs text-text-muted"><?= implode('<span aria-hidden="true" class="mx-1.5">·</span>', $details) ?></span>
                        </div>
                    </div>
                    <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($a['id']) ?>/delete"
                          data-confirm="Descartar este rascunho?">
                        <?= Csrf::field() ?>
                        <button type="submit" title="Descartar" aria-label="Descartar"
                                class="shrink-0 rounded-md p-1.5 text-text-muted transition-colors hover:bg-danger/10 hover:text-danger">
                            <?= $deleteIcon ?>
                        </button>
                    </form>
                </div>
                <?php if ($isGenerating): ?>
                    <?php $isStale = strtotime((string) $a['created_at']) <= strtotime('-15 minutes'); ?>
                    <?php if ($isStale): ?>
                        <p class="text-xs text-warning">
                            Isso está demorando mais que o esperado — provavelmente o processo em
                            segundo plano (worker) não está rodando. Avise o time técnico.
                        </p>
                    <?php else: ?>
                        <div class="loading-bar-track" role="progressbar" aria-label="A IA está gerando este rascunho">
                            <div class="loading-bar-fill"></div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($totalPages > 1): ?>
        <?php $pagerBtn = 'btn btn-secondary px-3 py-1.5 text-sm'; ?>
        <nav class="mt-5 flex items-center justify-center gap-3 text-sm" aria-label="Paginação">
            <?php if ($page > 1): ?>
                <a href="<?= View::e($pageUrl($page - 1)) ?>" class="<?= $pagerBtn ?>">← Anterior</a>
            <?php else: ?>
                <span class="btn btn-secondary px-3 py-1.5 text-sm" aria-disabled="true">← Anterior</span>
            <?php endif; ?>
            <span class="text-text-secondary">Página <?= $page ?> de <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="<?= View::e($pageUrl($page + 1)) ?>" class="<?= $pagerBtn ?>">Próxima →</a>
            <?php else: ?>
                <span class="btn btn-secondary px-3 py-1.5 text-sm" aria-disabled="true">Próxima →</span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
    </div>
<?php endif; ?>

<script>
    // Filtro em tempo real (pedido do responsável, 2026-09-17): categoria/
    // origem atualizam na hora (são escolhas discretas); a busca por texto
    // espera uma pausa curta de digitação (debounce) — senão cada tecla
    // dispararia uma consulta nova. Nada de recarregar a página: a primeira
    // versão fazia isso e a cada pausa a tela inteira piscava e o cursor
    // voltava pro começo do campo (a letra seguinte entrava no início do
    // texto). Agora busca a MESMA URL (?category_id=…&q=…, continua
    // compartilhável/atualizável com F5) via fetch, troca só as duas regiões
    // de resultado e deixa o formulário — e portanto foco e cursor — quietos.
    // Qualquer falha (rede, sessão expirada, HTML inesperado) cai numa
    // navegação normal pra mesma URL, então nunca deixa a tela numa versão velha.
    // `select-enhance.js` troca a caixa visível por um botão + lista própria,
    // mas o <select> real continua recebendo o evento `change` normal — é
    // nele que este script escuta, sem precisar saber do componente customizado.
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
                if (value !== '') params.append(key, value);
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
