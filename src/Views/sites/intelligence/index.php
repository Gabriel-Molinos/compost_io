<?php

declare(strict_types=1);

use App\Services\IntelligenceService;
use App\Support\Csrf;
use App\View;

/** @var array<string,mixed> $site */
/** @var array<string,mixed>|null $insight */

$activeTab = 'intelligence';
require __DIR__ . '/../_tabs.php';

$answers = $insight['answers'] ?? [];

$fmtDate = static function (?string $ts): string {
    if ($ts === null) {
        return '';
    }
    $d = date_create($ts);

    return $d === false ? $ts : $d->format('d/m/Y H:i');
};
?>
<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h2 class="text-lg font-semibold text-text-primary">Centro de Inteligência Editorial</h2>
        <p class="mt-1 max-w-2xl text-sm text-text-secondary">
            Análise narrativa da operação, gerada pela IA a partir dos números dos
            últimos meses e dos motivos de rejeição. Cada geração <strong>tem custo</strong>.
        </p>
    </div>
    <form method="post" action="/sites/<?= View::e($site['id']) ?>/intelligence/generate"
          onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Analisando… (pode levar 1 min)';">
        <?= Csrf::field() ?>
        <button type="submit"
                class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-light">
            <?= $insight === null ? 'Gerar análise' : 'Regenerar' ?>
        </button>
    </form>
</div>

<?php if ($insight === null): ?>
    <p class="mt-8 rounded-lg border border-border bg-surface p-6 text-sm text-text-secondary">
        Nenhuma análise ainda. Clique em <strong>Gerar análise</strong> para a IA responder:
        o que está funcionando, o que está dando errado, o que melhorou, o que não
        melhorou, o que a IA aprendeu e o que precisa ser ajustado.
    </p>
<?php else: ?>
    <p class="mt-4 text-xs text-text-muted">
        Gerada em <?= View::e($fmtDate($insight['created_at'])) ?>
        <?php if (($insight['generated_by_name'] ?? null) !== null): ?>
            por <?= View::e($insight['generated_by_name']) ?>
        <?php endif; ?>
        · <?= View::e($insight['model']) ?>
        · US$ <?= number_format((float) $insight['cost'], 2) ?>
    </p>

    <div class="mt-5 space-y-4">
        <?php foreach (IntelligenceService::QUESTIONS as $key => $label): ?>
            <section class="rounded-lg border border-border bg-surface p-4">
                <h3 class="text-sm font-semibold text-text-primary"><?= View::e($label) ?></h3>
                <?php if ($key === 'ajustes'): ?>
                    <?php $items = is_array($answers['ajustes'] ?? null) ? $answers['ajustes'] : []; ?>
                    <?php if ($items === []): ?>
                        <p class="mt-2 text-sm text-text-secondary">—</p>
                    <?php else: ?>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-text-secondary">
                            <?php foreach ($items as $item): ?>
                                <li><?= View::e((string) $item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="mt-2 whitespace-pre-line text-sm text-text-secondary">
                        <?= View::e((string) ($answers[$key] ?? '—')) ?>
                    </p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
