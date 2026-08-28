<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\View;

/** @var array<string,mixed> $site */
/** @var list<array<string,mixed>> $articles */
/** @var list<array<string,mixed>> $goals */
/** @var list<array<string,mixed>> $categories */

$activeTab = 'production';
require __DIR__ . '/../_tabs.php';

$statusLabels = [
    'PLANNED' => 'Planejado', 'IN_PROGRESS' => 'Em produção', 'IN_REVIEW' => 'Em revisão',
    'REVISION_REQUESTED' => 'Revisão pedida', 'APPROVED' => 'Aprovado', 'SCHEDULED' => 'Agendado',
    'PUBLISHED' => 'Publicado', 'DISCARDED' => 'Descartado', 'BLOCKED' => 'Bloqueado',
];
?>
<h2 class="text-lg font-semibold text-text-primary">Produção</h2>
<p class="mt-1 text-sm text-text-secondary">
    A IA gera um rascunho (planejamento → pesquisa → escrita). Cada geração faz
    várias chamadas ao Gemini e <strong>tem custo</strong> — gere poucos por vez.
</p>

<form method="post" action="/sites/<?= View::e($site['id']) ?>/production/generate"
      class="mt-5 flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4"
      onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Gerando… (pode levar 1–2 min)';">
    <?= Csrf::field() ?>
    <label class="text-sm">
        <span class="block font-medium text-text-secondary">Meta</span>
        <select name="goal_id" class="mt-1 rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-cyan focus:outline-none">
            <option value="">— (sem meta)</option>
            <?php foreach ($goals as $g): ?>
                <option value="<?= View::e($g['id']) ?>"><?= View::e($g['period']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="text-sm">
        <span class="block font-medium text-text-secondary">Categoria</span>
        <select name="category_id" class="mt-1 rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-cyan focus:outline-none">
            <option value="">— (a IA escolhe)</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= View::e($c['id']) ?>"><?= View::e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <button type="submit" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-light">
        Gerar rascunho
    </button>
</form>

<?php if ($articles === []): ?>
    <p class="mt-6 text-text-secondary">Nenhum artigo produzido ainda.</p>
<?php else: ?>
    <ul class="mt-6 divide-y divide-border rounded-lg border border-border">
        <?php foreach ($articles as $a): ?>
            <li class="flex items-start justify-between gap-4 px-4 py-3">
                <div class="min-w-0">
                    <a href="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($a['id']) ?>"
                       class="text-text-primary hover:text-cyan">
                        <?= View::e($a['title'] ?: 'Rascunho #' . $a['id']) ?>
                    </a>
                    <p class="mt-0.5 text-xs text-text-muted">
                        <?= View::e($statusLabels[$a['status']] ?? $a['status']) ?>
                        <?php if ((int) ($a['attempt_number'] ?? 1) > 1): ?> · tentativa <?= View::e($a['attempt_number']) ?><?php endif; ?>
                        <?php if (!empty($a['category_name'])): ?> · <?= View::e($a['category_name']) ?><?php endif; ?>
                        <?php if (!empty($a['word_count'])): ?> · <?= View::e($a['word_count']) ?> palavras<?php endif; ?>
                        · custo ~US$ <?= number_format((float) $a['ai_cost'], 4) ?>
                    </p>
                </div>
                <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($a['id']) ?>/delete"
                      onsubmit="return confirm('Descartar este rascunho?');">
                    <?= Csrf::field() ?>
                    <button type="submit" class="shrink-0 text-sm text-text-muted hover:text-danger">Descartar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
