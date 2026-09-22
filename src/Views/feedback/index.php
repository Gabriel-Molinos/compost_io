<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Icon;
use App\View;

/** @var bool $isAdmin */
/** @var list<array<string, mixed>> $sites */
/** @var list<array<string, mixed>> $entries */

$star = static fn (bool $filled): string => $filled
    ? '<path fill="currentColor" stroke="none" d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.9L12 3.5Z"/>'
    : '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.9L12 3.5Z"/>';

// Resumo do topo — direto de $entries, sem consulta nova (mesmo padrão de
// sources/links/index.php: computa em PHP em vez de outra ida ao banco).
$rated = array_values(array_filter($entries, static fn ($e) => $e['rating'] !== null));
$avgRating = $rated !== [] ? array_sum(array_map(static fn ($e) => (int) $e['rating'], $rated)) / count($rated) : null;
$pendingCount = count(array_filter($entries, static fn ($e) => empty($e['reviewed_at'])));
?>
<div class="flex items-start gap-3">
    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('feedback') ?></span>
    <div>
        <h1 class="font-display text-2xl font-bold text-text-primary">Feedback</h1>
        <p class="mt-1 max-w-2xl text-sm text-text-secondary">
            Avalie como o COMPOST em si está indo — não é sobre o conteúdo gerado
            (isso fica na Memória Editorial de cada site), é sobre a plataforma:
            o que percebeu, o que trava, o que está bom. É pra mim e pro Claude
            Code melhorarmos o app, não pra "ensinar" a IA.
        </p>
    </div>
</div>

<?php if ($entries !== []): ?>
    <div class="mt-6 grid gap-3 sm:grid-cols-3">
        <div class="article-card article-card--cyan rounded-xl" style="--card-enter: 0ms">
            <div class="flex items-center gap-3 p-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-cyan/15 text-cyan"><?= Icon::nav('feedback') ?></span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted"><?= $isAdmin ? 'Recebidos' : 'Enviados' ?></p>
                    <p class="mt-0.5 font-mono text-2xl font-semibold text-text-primary"><?= count($entries) ?></p>
                </div>
            </div>
        </div>
        <?php if ($isAdmin): ?>
            <div class="article-card <?= $pendingCount > 0 ? 'article-card--warning' : 'article-card--muted' ?> rounded-xl" style="--card-enter: 50ms">
                <div class="flex items-center gap-3 p-4">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-warning/15 text-warning"><?= Icon::nav('clock') ?></span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Pendentes</p>
                        <p class="mt-0.5 font-mono text-2xl font-semibold text-text-primary"><?= $pendingCount ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="article-card article-card--muted rounded-xl" style="--card-enter: 100ms">
            <div class="flex items-center gap-3 p-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-border/40 text-text-secondary [&>svg]:h-4 [&>svg]:w-4">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><?= $star(true) ?></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text-muted">Nota média</p>
                    <p class="mt-0.5 font-mono text-2xl font-semibold text-text-primary"><?= $avgRating !== null ? number_format($avgRating, 1) : '—' ?></p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<section class="mt-6 overflow-hidden rounded-xl border border-border bg-surface">
    <div class="border-b border-border bg-surface-2/40 px-5 py-3">
        <h2 class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('plus') ?></span>
            Enviar feedback
        </h2>
    </div>

    <form method="post" action="/feedback" class="space-y-4 p-5">
        <?= Csrf::field() ?>

        <div>
            <span class="block text-sm font-medium text-text-secondary">Nota geral (opcional)</span>
            <div class="mt-2 flex items-center gap-1" data-star-picker>
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label class="cursor-pointer text-text-muted hover:text-cyan" data-star-label="<?= $i ?>">
                        <input type="radio" name="rating" value="<?= $i ?>" class="sr-only" data-star-input>
                        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><?= $star(false) ?></svg>
                    </label>
                <?php endfor; ?>
                <button type="button" data-star-clear class="ml-2 text-xs text-text-muted hover:text-text-primary">limpar</button>
            </div>
        </div>

        <?php if ($sites !== []): ?>
            <div>
                <label for="f_site" class="block text-sm font-medium text-text-secondary">Sobre um site específico? (opcional)</label>
                <select id="f_site" name="site_id" class="mt-1 w-full">
                    <option value="">Sobre a plataforma em geral</option>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?= View::e($site['id']) ?>"><?= View::e($site['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div>
            <label for="f_message" class="block text-sm font-medium text-text-secondary">
                O que você percebeu? <span class="text-danger" aria-hidden="true">*</span>
            </label>
            <textarea id="f_message" name="message" rows="4" required
                      placeholder="Ex.: o gerador de imagem trava direto no fim do dia, ou: a revisão ficou bem mais rápida depois do checklist novo..."
                      class="mt-1 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-text-primary placeholder:text-text-muted focus:border-cyan focus:outline-none"></textarea>
        </div>

        <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
            <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('feedback') ?></span>
            Enviar feedback
        </button>
    </form>
</section>

<section class="mt-6">
    <h2 class="flex items-center gap-2 font-display text-base font-semibold text-text-primary">
        <span class="flex h-7 w-7 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('feedback') ?></span>
        <?= $isAdmin ? 'Feedback recebido' : 'Seu histórico' ?>
    </h2>

    <?php if ($entries === []): ?>
        <div class="mt-3 rounded-xl border border-dashed border-border-strong p-8 text-center">
            <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-border/40 text-text-muted [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('feedback') ?></span>
            <p class="mt-3 text-sm text-text-secondary">Nenhum feedback ainda.</p>
        </div>
    <?php else: ?>
        <ul class="mt-3 space-y-2.5">
            <?php foreach ($entries as $i => $entry): ?>
                <?php $reviewed = !empty($entry['reviewed_at']); ?>
                <li class="hover-card overflow-hidden rounded-xl border-l-2 <?= $reviewed ? 'border-l-border-strong' : 'border-l-warning' ?> border-y border-r border-border bg-surface p-4"
                    style="animation: fade-in-up 240ms ease backwards; animation-delay: <?= min($i, 10) * 40 ?>ms">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <?php if ($isAdmin): ?>
                                <?= Avatar::html($entry['author_avatar'] ?? null, (string) $entry['author_name'], size: 'h-9 w-9', radius: 'rounded-full', textSize: 'text-sm') ?>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-text-muted">
                                    <?php if ($isAdmin): ?>
                                        <span class="font-medium text-text-secondary"><?= View::e($entry['author_name']) ?></span>
                                        <span aria-hidden="true">·</span>
                                    <?php endif; ?>
                                    <?php if (!empty($entry['site_name'])): ?>
                                        <span class="rounded-full bg-surface-2 px-2 py-0.5 font-medium text-text-secondary"><?= View::e($entry['site_name']) ?></span>
                                    <?php else: ?>
                                        <span class="rounded-full bg-surface-2 px-2 py-0.5 font-medium text-text-secondary">plataforma em geral</span>
                                    <?php endif; ?>
                                    <span aria-hidden="true">·</span>
                                    <span><?= View::e(date('d/m/Y H:i', strtotime((string) $entry['created_at']))) ?></span>
                                </div>
                                <?php if ($entry['rating'] !== null): ?>
                                    <div class="mt-1.5 flex items-center gap-0.5 text-cyan">
                                        <?php for ($i2 = 1; $i2 <= 5; $i2++): ?>
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><?= $star($i2 <= (int) $entry['rating']) ?></svg>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                                <p class="mt-1.5 text-sm text-text-primary"><?= nl2br(View::e($entry['message'])) ?></p>
                            </div>
                        </div>

                        <?php if ($isAdmin): ?>
                            <?php if ($reviewed): ?>
                                <span class="flex shrink-0 items-center gap-1 rounded-full bg-success/15 px-2.5 py-1 text-[11px] font-medium text-success">
                                    <span class="[&>svg]:h-3 [&>svg]:w-3"><?= Icon::nav('check') ?></span>
                                    Visto por <?= View::e($entry['reviewer_name']) ?>
                                </span>
                            <?php else: ?>
                                <form method="post" action="/feedback/<?= View::e($entry['id']) ?>/review">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="btn btn-secondary shrink-0 px-2.5 py-1 text-xs">
                                        <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav('check') ?></span>
                                        Marcar como visto
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<script>
    (function () {
        var picker = document.querySelector('[data-star-picker]');
        if (!picker) return;
        var labels = picker.querySelectorAll('[data-star-label]');
        var clearBtn = picker.querySelector('[data-star-clear]');

        function paint(selected) {
            labels.forEach(function (label) {
                var value = parseInt(label.getAttribute('data-star-label'), 10);
                label.classList.toggle('text-cyan', value <= selected);
                label.classList.toggle('text-text-muted', value > selected);
                var svg = label.querySelector('svg');
                svg.setAttribute('fill', value <= selected ? 'currentColor' : 'none');
            });
        }

        labels.forEach(function (label) {
            label.addEventListener('click', function () {
                paint(parseInt(label.getAttribute('data-star-label'), 10));
            });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                picker.querySelectorAll('[data-star-input]').forEach(function (input) { input.checked = false; });
                paint(0);
            });
        }
    })();
</script>
