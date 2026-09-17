<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
use App\View;

/** @var bool $isAdmin */
/** @var list<array<string, mixed>> $sites */
/** @var list<array<string, mixed>> $entries */

$star = static fn (bool $filled): string => $filled
    ? '<path fill="currentColor" stroke="none" d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.9L12 3.5Z"/>'
    : '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.8l-5.2 2.8 1-5.8-4.3-4.1 5.9-.9L12 3.5Z"/>';
?>
<div class="flex items-start gap-3">
    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('feedback') ?></span>
    <div>
        <h1 class="font-display text-2xl font-bold text-text-primary">Feedback</h1>
        <p class="mt-1 text-sm text-text-secondary">
            Avalie como o COMPOST em si está indo — não é sobre o conteúdo gerado
            (isso fica na Memória Editorial de cada site), é sobre a plataforma:
            o que percebeu, o que trava, o que está bom. É pra mim e pro Claude
            Code melhorarmos o app, não pra "ensinar" a IA.
        </p>
    </div>
</div>

<section class="mt-6 rounded-lg border border-border bg-surface p-5">
    <h2 class="font-display text-base font-semibold text-text-primary">Enviar feedback</h2>

    <form method="post" action="/feedback" class="mt-4 space-y-4">
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

        <button type="submit" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            Enviar feedback
        </button>
    </form>
</section>

<section class="mt-6">
    <h2 class="font-display text-base font-semibold text-text-primary">
        <?= $isAdmin ? 'Feedback recebido' : 'Seu histórico' ?>
    </h2>

    <?php if ($entries === []): ?>
        <p class="mt-3 text-sm text-text-secondary">Nenhum feedback ainda.</p>
    <?php else: ?>
        <ul class="mt-3 space-y-2">
            <?php foreach ($entries as $entry): ?>
                <?php $reviewed = !empty($entry['reviewed_at']); ?>
                <li class="rounded-lg border border-border bg-surface p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 text-xs text-text-muted">
                                <?php if ($isAdmin): ?>
                                    <span class="font-medium text-text-secondary"><?= View::e($entry['author_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($entry['site_name'])): ?>
                                    <span>· <?= View::e($entry['site_name']) ?></span>
                                <?php else: ?>
                                    <span>· plataforma em geral</span>
                                <?php endif; ?>
                                <span>· <?= View::e(date('d/m/Y H:i', strtotime((string) $entry['created_at']))) ?></span>
                            </div>
                            <?php if ($entry['rating'] !== null): ?>
                                <div class="mt-1.5 flex items-center gap-0.5 text-cyan">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><?= $star($i <= (int) $entry['rating']) ?></svg>
                                    <?php endfor; ?>
                                </div>
                            <?php endif; ?>
                            <p class="mt-1.5 text-sm text-text-primary"><?= nl2br(View::e($entry['message'])) ?></p>
                        </div>

                        <?php if ($isAdmin): ?>
                            <?php if ($reviewed): ?>
                                <span class="shrink-0 rounded-full bg-success/15 px-2 py-0.5 text-[11px] font-medium text-success">
                                    Visto por <?= View::e($entry['reviewer_name']) ?>
                                </span>
                            <?php else: ?>
                                <form method="post" action="/feedback/<?= View::e($entry['id']) ?>/review">
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="shrink-0 rounded-md border border-border px-2.5 py-1 text-xs text-text-secondary hover:border-cyan hover:text-text-primary">
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
