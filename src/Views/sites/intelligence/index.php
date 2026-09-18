<?php

declare(strict_types=1);

use App\Services\IntelligenceService;
use App\Support\Csrf;
use App\Support\Icon;
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

// Tom por pergunta — a mesma leitura visual da memória editorial (borda de
// acento colorida) em vez de 6 caixas cinzas idênticas competindo igual
// pela atenção. Pares lado a lado fazem sentido de leitura: estado atual
// (funcionando/dando errado) → evolução (melhorou/não melhorou) →
// conclusão (aprendeu/ajustes).
$tone = [
    'funcionando'  => 'success',
    'dando_errado' => 'danger',
    'melhorou'     => 'success',
    'nao_melhorou' => 'warning',
    'ia_aprendeu'  => 'cyan',
    'ajustes'      => 'warning',
];
$toneBorder = static fn (string $t): string => match ($t) {
    'success' => 'border-l-success', 'danger' => 'border-l-danger', 'warning' => 'border-l-warning', default => 'border-l-cyan',
};
$toneDot = static fn (string $t): string => match ($t) {
    'success' => 'bg-success', 'danger' => 'bg-danger', 'warning' => 'bg-warning', default => 'bg-cyan',
};
?>
<div class="flex flex-wrap items-start justify-between gap-4" data-tour="intelligence-header">
    <div class="flex items-start gap-3">
        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('intelligence') ?></span>
        <div>
            <h1 class="font-display text-2xl font-bold text-text-primary">Centro de Inteligência Editorial</h1>
            <p class="mt-1 max-w-2xl text-sm text-text-secondary">
                Análise narrativa da operação, gerada pela IA a partir dos números dos
                últimos meses e dos motivos de rejeição. Cada geração <strong class="text-text-primary">tem custo</strong>.
            </p>
        </div>
    </div>
    <form method="post" action="/sites/<?= View::e($site['id']) ?>/intelligence/generate"
          onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Analisando… (pode levar 1 min)';">
        <?= Csrf::field() ?>
        <button type="submit"
                class="btn btn-primary px-4 py-2 text-sm">
            <?= $insight === null ? 'Gerar análise' : 'Regenerar' ?>
        </button>
    </form>
</div>

<?php if ($insight === null): ?>
    <div class="mt-8 flex flex-col items-center gap-3 rounded-lg border border-dashed border-border bg-surface p-10 text-center">
        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-cyan/10 text-cyan"><?= Icon::nav('intelligence') ?></span>
        <p class="max-w-md text-sm text-text-secondary">
            Nenhuma análise ainda. Clique em <strong class="text-text-primary">Gerar análise</strong> pra IA responder:
            o que está funcionando, o que está dando errado, o que melhorou, o que não
            melhorou, o que a IA aprendeu e o que precisa ser ajustado.
        </p>
    </div>
<?php else: ?>
    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-text-muted">
        <span>Gerada em <?= View::e($fmtDate($insight['created_at'])) ?></span>
        <?php if (($insight['generated_by_name'] ?? null) !== null): ?>
            <span>· por <?= View::e($insight['generated_by_name']) ?></span>
        <?php endif; ?>
        <span class="rounded-full bg-surface-2 px-2 py-0.5 font-mono"><?= View::e($insight['model']) ?></span>
        <span class="rounded-full bg-surface-2 px-2 py-0.5 font-mono">US$ <?= number_format((float) $insight['cost'], 2) ?></span>
    </div>

    <div class="mt-5 grid gap-4 lg:grid-cols-2">
        <?php foreach (IntelligenceService::QUESTIONS as $key => $label): ?>
            <?php $t = $tone[$key] ?? 'cyan'; ?>
            <section class="rounded-lg border-l-2 <?= $toneBorder($t) ?> border-y border-r border-border bg-surface p-5">
                <h2 class="flex items-center gap-2 font-display text-sm font-semibold text-text-primary">
                    <span aria-hidden="true" class="h-1.5 w-1.5 shrink-0 rounded-full <?= $toneDot($t) ?>"></span>
                    <?= View::e($label) ?>
                </h2>
                <?php if ($key === 'ajustes'): ?>
                    <?php $items = is_array($answers['ajustes'] ?? null) ? $answers['ajustes'] : []; ?>
                    <?php if ($items === []): ?>
                        <p class="mt-2 text-sm text-text-muted">—</p>
                    <?php else: ?>
                        <ul class="mt-3 space-y-2">
                            <?php foreach ($items as $item): ?>
                                <li class="flex items-start gap-2 text-sm text-text-secondary">
                                    <span aria-hidden="true" class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-text-muted"></span>
                                    <span><?= View::e((string) $item) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-text-secondary">
                        <?= View::e((string) ($answers[$key] ?? '—')) ?>
                    </p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
