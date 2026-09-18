<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var list<array<string, mixed>> $notifications Mais recentes primeiro (até 50) */

$total = count($notifications);
$unread = count(array_filter($notifications, static fn (array $n): bool => $n['read_at'] === null));

// Ícone por tipo (o tom vem de Labels::notificationTone, o mesmo do card e da etiqueta).
$typeIcon = static fn (string $type): string => match ($type) {
    'PUBLISH_SUCCESS' => 'check',
    'PUBLISH_FAILED', 'ATTENTION' => 'alert',
    'SITE_ASSIGNED'   => 'sites',
    'ARTICLE_READY'   => 'production',
    'FEEDBACK'        => 'feedback',
    default           => 'bell',
};

// "há 5 min", "há 3 h", "há 2 d" ou a data — o horário completo fica no title.
$ago = static function (int $ts): string {
    $d = max(0, time() - $ts);
    if ($d < 60) {
        return 'agora';
    }
    if ($d < 3600) {
        return 'há ' . intdiv($d, 60) . ' min';
    }
    if ($d < 86400) {
        return 'há ' . intdiv($d, 3600) . ' h';
    }
    if ($d < 7 * 86400) {
        return 'há ' . intdiv($d, 86400) . ' d';
    }

    return date('d/m/Y', $ts);
};

// Grupos por dia (ordem já vem do banco: mais recente primeiro).
$startToday = strtotime('today');
$groupOf = static function (int $ts) use ($startToday): string {
    if ($ts >= $startToday) {
        return 'Hoje';
    }
    if ($ts >= $startToday - 86400) {
        return 'Ontem';
    }
    if ($ts >= $startToday - 6 * 86400) {
        return 'Esta semana';
    }

    return 'Mais antigas';
};
$groups = [];
$types = [];
foreach ($notifications as $n) {
    $groups[$groupOf((int) strtotime((string) $n['created_at']))][] = $n;
    $types[(string) $n['type']] = ($types[(string) $n['type']] ?? 0) + 1;
}
$cardIndex = 0;
?>
<?php // ── Cabeçalho: contador grande das não lidas, com ação de marcar tudo ── ?>
<section class="article-card <?= $unread > 0 ? 'article-card--cyan' : 'article-card--success' ?> hover-card rounded-3xl" data-notif-hero style="--card-phase: -1.6s">
    <span class="article-card-fx" aria-hidden="true"></span>
    <span class="article-card-tags">
        <span class="article-card-tag <?= $unread > 0 ? 'article-card-tag--cyan' : 'article-card-tag--success' ?>" data-notif-hero-tag>
            <?php if ($unread > 0): ?>
                <span aria-hidden="true" class="status-dot h-1.5 w-1.5 rounded-full bg-current"></span>Novas
            <?php else: ?>
                <?= Icon::nav('check') ?>Tudo em dia
            <?php endif; ?>
        </span>
    </span>

    <div class="flex flex-wrap items-center gap-6 p-6 pt-8 sm:gap-8">
        <span class="relative flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-void text-cyan shadow-[0_0_0_2px_rgba(0,208,240,.5),0_0_36px_-6px_rgba(0,208,240,.6)] [&>svg]:h-9 [&>svg]:w-9" data-notif-bell>
            <?= Icon::nav('bell') ?>
            <?php if ($unread > 0): ?><span aria-hidden="true" class="notif-ring"></span><?php endif; ?>
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold uppercase tracking-widest text-text-muted">Notificações</p>
            <p class="mt-1 flex items-baseline gap-3">
                <span class="<?= $unread === 0 ? 'font-sans' : 'font-display' ?> text-5xl font-bold leading-none text-text-primary" data-notif-unread><?= $unread ?></span>
                <span class="text-lg font-semibold text-text-secondary" data-notif-unread-label><?= $unread === 1 ? 'não lida' : 'não lidas' ?></span>
            </p>
            <p class="mt-2 text-sm text-text-muted"><?= $total ?> <?= $total === 1 ? 'notificação' : 'notificações' ?> no total<?= $total >= 50 ? ' (as 50 mais recentes)' : '' ?>.</p>
        </div>

        <form method="post" action="/notifications/read-all" data-mark-all class="<?= $unread > 0 ? '' : 'hidden' ?>">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-primary px-5 py-2.5 text-sm">
                <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('check') ?></span>
                Marcar todas como lidas
            </button>
        </form>
    </div>
</section>

<?php if ($notifications === []): ?>
    <div class="mt-8 rounded-2xl border border-dashed border-border-strong p-12 text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-cyan/10 text-cyan [&>svg]:h-7 [&>svg]:w-7"><?= Icon::nav('bell') ?></span>
        <p class="mt-4 font-display text-lg font-semibold text-text-primary">Nenhuma notificação ainda</p>
        <p class="mx-auto mt-1 max-w-md text-sm text-text-secondary">
            Quando um post for publicado, algo precisar de atenção ou você for vinculado a um site, o aviso aparece aqui.
        </p>
    </div>
<?php else: ?>
    <?php // ── Filtros instantâneos (a lista já está na página: nada de recarregar) ── ?>
    <div class="mt-6 rounded-2xl border border-border bg-surface p-4" data-notif-filters>
        <div class="relative">
            <span aria-hidden="true" class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-cyan [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('search') ?></span>
            <label for="f_notif_q" class="sr-only">Buscar notificações</label>
            <input type="search" id="f_notif_q" data-notif-search autocomplete="off" placeholder="Buscar por título, mensagem ou site…"
                   class="w-full rounded-xl border border-border bg-surface-2 py-2.5 pl-10 pr-4 text-text-primary placeholder:text-text-muted focus:border-cyan focus:outline-none">
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-3">
            <div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-label="Situação">
                <span class="mr-1 text-[11px] font-bold uppercase tracking-widest text-text-muted">Situação</span>
                <?php foreach ([['all', 'Todas', $total, ''], ['unread', 'Não lidas', $unread, ''], ['read', 'Lidas', $total - $unread, '']] as [$value, $label, $n, $tone]): ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="notif_status" value="<?= $value ?>" class="peer sr-only" <?= $value === 'all' ? 'checked' : '' ?>>
                        <span class="pick pick-chip"><?= View::e($label) ?> <span class="font-mono" data-count-status="<?= $value ?>"><?= $n ?></span></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (count($types) > 1): ?>
                <div class="flex flex-wrap items-center gap-2" role="radiogroup" aria-label="Tipo">
                    <span class="mr-1 text-[11px] font-bold uppercase tracking-widest text-text-muted">Tipo</span>
                    <label class="cursor-pointer">
                        <input type="radio" name="notif_type" value="" class="peer sr-only" checked>
                        <span class="pick pick-chip">Todos</span>
                    </label>
                    <?php foreach ($types as $type => $n): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="notif_type" value="<?= View::e($type) ?>" class="peer sr-only">
                            <span class="pick pick-chip"><?= View::e(Labels::notificationTypeLabel($type)) ?> <span class="font-mono"><?= $n ?></span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <p data-notif-empty class="mt-6 hidden rounded-2xl border border-dashed border-border-strong p-10 text-center text-sm text-text-secondary">
        Nenhuma notificação bate com esses filtros.
    </p>

    <div class="mt-2" data-notif-list>
        <?php foreach ($groups as $groupLabel => $items): ?>
            <section data-notif-group class="mt-9">
                <h2 class="flex items-center gap-3 text-[11px] font-bold uppercase tracking-[.2em] text-text-muted">
                    <?= View::e($groupLabel) ?>
                    <span class="rounded-full bg-white/5 px-2 py-0.5 font-mono text-[10px] tracking-normal" data-group-count><?= count($items) ?></span>
                    <span aria-hidden="true" class="h-px flex-1 bg-gradient-to-r from-border-strong to-transparent"></span>
                </h2>

                <ul class="mt-6 space-y-7">
                    <?php foreach ($items as $n): ?>
                        <?php
                        $isUnread = $n['read_at'] === null;
                        $type = (string) $n['type'];
                        $tone = Labels::notificationTone($type);
                        $toneClass = Labels::articleCardTone($tone);
                        $mutedClass = Labels::articleCardTone('muted');
                        $ts = (int) strtotime((string) $n['created_at']);
                        $hasLink = !empty($n['link']);
                        $cardStyle = sprintf('--card-enter: %dms; --card-phase: -%.2Fs', min($cardIndex, 10) * 35, ($cardIndex % 7) * 0.85);
                        $cardIndex++;
                        $search = mb_strtolower(implode(' ', [(string) $n['title'], (string) $n['message'], (string) ($n['site_name'] ?? ''), Labels::notificationTypeLabel($type)]));
                        ?>
                        <li class="article-card hover-card notif-card <?= $isUnread ? $toneClass : $mutedClass ?> rounded-2xl"
                            style="<?= $cardStyle ?>"
                            data-notif="<?= View::e($n['id']) ?>" data-type="<?= View::e($type) ?>" data-read="<?= $isUnread ? '0' : '1' ?>"
                            data-tone-class="<?= $toneClass ?>" data-muted-class="<?= $mutedClass ?>"
                            data-text="<?= View::e($search) ?>" <?= $hasLink ? 'data-open' : '' ?>>
                            <span class="article-card-fx" aria-hidden="true"></span>
                            <span class="article-card-tags" style="left: 1.25rem; right: auto">
                                <span class="article-card-tag">
                                    <?= Icon::nav($typeIcon($type)) ?>
                                    <?= View::e(Labels::notificationTypeLabel($type)) ?>
                                </span>
                            </span>

                            <div class="flex flex-wrap items-start gap-4 p-5 pt-7 sm:flex-nowrap">
                                <span class="notif-icon flex h-12 w-12 shrink-0 items-center justify-center rounded-full [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav($typeIcon($type)) ?></span>

                                <div class="min-w-0 flex-1">
                                    <p class="flex items-center gap-2">
                                        <span aria-hidden="true" class="notif-dot status-dot h-2 w-2 shrink-0 rounded-full bg-current <?= $isUnread ? '' : 'hidden' ?>"></span>
                                        <span class="notif-title font-display text-lg font-semibold"><?= View::e($n['title']) ?></span>
                                    </p>
                                    <p class="mt-1.5 text-sm leading-relaxed text-text-secondary"><?= View::e($n['message']) ?></p>

                                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                                        <?php if (!empty($n['site_name'])): ?>
                                            <span class="article-card-fact article-card-fact--chip">
                                                <span class="article-card-icon"><?= Icon::nav('sites') ?></span>
                                                <?= View::e($n['site_name']) ?>
                                            </span>
                                        <?php endif; ?>
                                        <time datetime="<?= View::e(date('c', $ts)) ?>" title="<?= View::e(date('d/m/Y H:i', $ts)) ?>" class="article-card-fact">
                                            <span class="article-card-icon"><?= Icon::nav('clock') ?></span>
                                            <?= View::e($ago($ts)) ?>
                                        </time>
                                    </div>
                                </div>

                                <div class="relative z-10 flex shrink-0 flex-col items-stretch gap-2 sm:items-end">
                                    <?php if ($hasLink): ?>
                                        <form method="post" action="/notifications/<?= View::e($n['id']) ?>/open">
                                            <?= Csrf::field() ?>
                                            <button type="submit" class="btn btn-primary group/open px-4 py-2 text-sm">
                                                Abrir
                                                <span class="transition-transform duration-200 group-hover/open:translate-x-1 [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('arrow') ?></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" data-notif-toggle class="btn btn-secondary px-3 py-1.5 text-xs">
                                        <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav('check') ?></span>
                                        <span data-toggle-label><?= $isUnread ? 'Marcar como lida' : 'Marcar como não lida' ?></span>
                                    </button>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>

    <script>
        // Interação da tela (melhoria progressiva — sem JS os botões continuam sendo formulários/links):
        //  · marcar UMA como lida/não lida e "marcar todas" sem recarregar (fetch → JSON), atualizando o
        //    contador grande, o selo do sino na sidebar e as cores do card;
        //  · clicar no card abre a notificação (o mesmo formulário do botão "Abrir");
        //  · busca e filtros instantâneos (a lista inteira já está na página).
        (function () {
            var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
            var list = document.querySelector('[data-notif-list]');
            var hero = document.querySelector('[data-notif-hero]');
            var unreadEl = document.querySelector('[data-notif-unread]');
            var unreadLabel = document.querySelector('[data-notif-unread-label]');
            var heroTag = document.querySelector('[data-notif-hero-tag]');
            var markAll = document.querySelector('[data-mark-all]');
            var bell = document.querySelector('[data-notif-bell]');
            var emptyMsg = document.querySelector('[data-notif-empty]');
            var search = document.querySelector('[data-notif-search]');
            if (!list) return;

            function cards() { return [].slice.call(list.querySelectorAll('[data-notif]')); }

            function post(url) {
                var body = new FormData();
                body.append('_token', token);
                return fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json().then(function (j) { if (!r.ok || !j.ok) { throw new Error(j.error || 'Falhou'); } return j; }); });
            }

            // Contadores: número grande (com "pulinho"), selo do sino na sidebar, tag e botão do cabeçalho.
            function setUnread(n) {
                if (unreadEl) {
                    unreadEl.textContent = String(n);
                    // Orbitron desenha o 0 cortado por uma barra: no zero usa a fonte de texto.
                    unreadEl.classList.toggle('font-display', n > 0);
                    unreadEl.classList.toggle('font-sans', n === 0);
                    unreadEl.classList.remove('notif-pop');
                    void unreadEl.offsetWidth;
                    unreadEl.classList.add('notif-pop');
                }
                if (unreadLabel) unreadLabel.textContent = n === 1 ? 'não lida' : 'não lidas';
                if (markAll) markAll.classList.toggle('hidden', n === 0);
                if (hero) {
                    hero.classList.toggle('article-card--cyan', n > 0);
                    hero.classList.toggle('article-card--success', n === 0);
                }
                if (heroTag) {
                    heroTag.className = 'article-card-tag ' + (n > 0 ? 'article-card-tag--cyan' : 'article-card-tag--success');
                    heroTag.innerHTML = n > 0
                        ? '<span aria-hidden="true" class="status-dot h-1.5 w-1.5 rounded-full bg-current"></span>Novas'
                        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>Tudo em dia';
                }
                if (bell) {
                    var ring = bell.querySelector('.notif-ring');
                    if (n > 0 && !ring) { ring = document.createElement('span'); ring.className = 'notif-ring'; ring.setAttribute('aria-hidden', 'true'); bell.appendChild(ring); }
                    if (n === 0 && ring) ring.remove();
                }
                var link = document.querySelector('a.dial-btn[href="/notifications"]');
                if (link) {
                    var badge = link.querySelector('.nav-badge');
                    if (n === 0 && badge) badge.remove();
                    if (n > 0) {
                        if (!badge) { badge = document.createElement('span'); badge.className = 'nav-badge'; link.insertBefore(badge, link.querySelector('.dial-btn-tip')); }
                        badge.textContent = n > 99 ? '99+' : String(n);
                    }
                }
                var statusUnread = document.querySelector('[data-count-status="unread"]');
                var statusRead = document.querySelector('[data-count-status="read"]');
                var totalCount = cards().length;
                if (statusUnread) statusUnread.textContent = String(n);
                if (statusRead) statusRead.textContent = String(totalCount - n);
            }

            // Visual de UM card (lida = tom apagado, sem animação; não lida = tom do tipo).
            function paint(card, read) {
                card.setAttribute('data-read', read ? '1' : '0');
                card.classList.remove(card.getAttribute('data-tone-class'), card.getAttribute('data-muted-class'));
                card.classList.add(card.getAttribute(read ? 'data-muted-class' : 'data-tone-class'));
                var dot = card.querySelector('.notif-dot');
                if (dot) dot.classList.toggle('hidden', read);
                var label = card.querySelector('[data-toggle-label]');
                if (label) label.textContent = read ? 'Marcar como não lida' : 'Marcar como lida';
            }

            function toggle(card, btn) {
                var wasRead = card.getAttribute('data-read') === '1';
                btn.disabled = true;
                post('/notifications/' + card.getAttribute('data-notif') + (wasRead ? '/unread' : '/read'))
                    .then(function (j) { paint(card, !wasRead); setUnread(j.unread); applyFilters(true); })
                    .catch(function (e) { window.alert(e.message); })
                    .then(function () { btn.disabled = false; });
            }

            list.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-notif-toggle]');
                var card = e.target.closest('[data-notif]');
                if (!card) return;
                if (btn) { e.preventDefault(); toggle(card, btn); return; }
                // Clique em qualquer lugar do card (fora dos botões) abre — quando há link.
                if (e.target.closest('a, button, form, time') || !card.hasAttribute('data-open')) return;
                var open = card.querySelector('form[action$="/open"]');
                if (open && open.requestSubmit) open.requestSubmit();
            });

            if (markAll) {
                markAll.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var btn = markAll.querySelector('button');
                    btn.disabled = true;
                    var body = new FormData();
                    body.append('_token', token);
                    fetch('/notifications/read-all', { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r.json(); })
                        .then(function (j) {
                            if (!j.ok) throw new Error(j.error || 'Falhou');
                            cards().forEach(function (c, i) { window.setTimeout(function () { paint(c, true); }, Math.min(i, 12) * 45); });
                            setUnread(0);
                            applyFilters(true);
                        })
                        .catch(function () { markAll.submit(); }) // qualquer falha: o envio normal do formulário
                        .then(function () { btn.disabled = false; });
                });
            }

            // ── Filtros instantâneos ────────────────────────────────────────────
            function value(name) { var r = document.querySelector('input[name="' + name + '"]:checked'); return r ? r.value : ''; }

            function applyFilters(soft) {
                var q = search ? search.value.trim().toLowerCase() : '';
                var status = value('notif_status');
                var type = value('notif_type');
                var shown = 0;
                cards().forEach(function (c) {
                    var read = c.getAttribute('data-read') === '1';
                    var ok = (status === 'all' || status === '' || (status === 'unread' && !read) || (status === 'read' && read))
                        && (type === '' || c.getAttribute('data-type') === type)
                        && (q === '' || c.getAttribute('data-text').indexOf(q) !== -1);
                    c.hidden = !ok;
                    if (ok) shown += 1;
                });
                [].slice.call(list.querySelectorAll('[data-notif-group]')).forEach(function (g) {
                    var n = g.querySelectorAll('[data-notif]:not([hidden])').length;
                    g.hidden = n === 0;
                    var count = g.querySelector('[data-group-count]');
                    if (count) count.textContent = String(n);
                });
                if (emptyMsg) emptyMsg.classList.toggle('hidden', shown > 0);
            }

            document.querySelectorAll('[data-notif-filters] input[type="radio"]').forEach(function (r) { r.addEventListener('change', function () { applyFilters(); }); });
            if (search) {
                search.addEventListener('input', function () { applyFilters(); });
                search.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
            }
        })();
    </script>
<?php endif; ?>
