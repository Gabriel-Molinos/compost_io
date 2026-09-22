<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Form;
use App\Support\Icon;
use App\View;

/** @var array<string, mixed> $site */
/** @var array<string, mixed> $rule */
/** @var string $action */
/** @var array<string, string> $errors */

$activeTab = 'rules';
require __DIR__ . '/../_tabs.php';

$isEdit = !empty($rule['id']);
$type = ($rule['type'] ?? 'INTEREST') === 'NON_INTEREST' ? 'NON_INTEREST' : 'INTEREST';
$intensity = (int) ($rule['intensity'] ?? 3);
$intensityLabels = ['', 'muito baixa', 'baixa', 'média', 'alta', 'muito alta'];

// Card e tag mudam de cor (ciano/âmbar) ao vivo conforme o tipo escolhido —
// mesma paleta de tom já usada em index.php, só que aqui é dinâmica (o JS no
// fim do arquivo troca --tone/--tint no wrapper quando o radio muda).
$toneCyan = '0 208 240';
$toneWarning = '255 197 61';
$initialTone = $type === 'NON_INTEREST' ? $toneWarning : $toneCyan;
$initialTint = $type === 'NON_INTEREST' ? '.32' : '0';
?>
<a href="/sites/<?= View::e($site['id']) ?>/rules" class="text-sm text-text-secondary hover:text-text-primary">
    ← Interesses
</a>

<div class="mt-3 flex items-center gap-3">
    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('rules') ?></span>
    <div>
        <h1 class="font-display text-2xl font-bold text-text-primary"><?= $isEdit ? 'Editar regra' : 'Nova regra' ?></h1>
        <p class="mt-1 text-sm text-text-secondary">
            Diz pra IA o que priorizar (ou evitar) na hora de escolher pauta pra <?= View::e($site['name']) ?>.
        </p>
    </div>
</div>

<?php if ($errors !== []): ?>
    <p role="alert" class="mt-5 flex items-center gap-2 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        <span class="shrink-0 [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('alert') ?></span>
        Corrija os campos destacados abaixo.
    </p>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="mt-6 max-w-2xl" novalidate>
    <?= Csrf::field() ?>

    <div id="rule-card" class="article-card rounded-2xl" style="--tone: <?= $initialTone ?>; --tint: <?= $initialTint ?>; --card-enter: 40ms">
        <span class="article-card-fx" aria-hidden="true"></span>
        <span class="article-card-tags">
            <span id="rule-type-tag" class="article-card-tag <?= $type === 'NON_INTEREST' ? 'article-card-tag--warning' : 'article-card-tag--cyan' ?>">
                <?= $type === 'NON_INTEREST' ? 'Não-interesse' : 'Interesse' ?>
            </span>
        </span>

        <div class="p-5 pt-8 sm:p-6 sm:pt-8">
            <fieldset>
                <legend class="text-sm font-medium text-text-secondary">Tipo</legend>
                <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="group flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-surface-2/60 p-4 transition-colors hover:border-border-strong has-[:checked]:border-cyan has-[:checked]:bg-cyan/10 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-cyan-bright has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-surface">
                        <input id="f_type_interest" type="radio" name="type" value="INTEREST" <?= $type === 'INTEREST' ? 'checked' : '' ?> class="sr-only" data-type-radio data-tone="<?= $toneCyan ?>" data-tint="0">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-cyan/15 text-cyan"><?= Icon::nav('rules') ?></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-text-primary">Interesse</span>
                            <span class="mt-0.5 block text-xs text-text-secondary">A IA prioriza pautas ligadas a isto.</span>
                        </span>
                    </label>
                    <label class="group flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-surface-2/60 p-4 transition-colors hover:border-border-strong has-[:checked]:border-warning has-[:checked]:bg-warning/10 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-cyan-bright has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-surface">
                        <input id="f_type_non" type="radio" name="type" value="NON_INTEREST" <?= $type === 'NON_INTEREST' ? 'checked' : '' ?> class="sr-only" data-type-radio data-tone="<?= $toneWarning ?>" data-tint=".32">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-warning/15 text-warning"><?= Icon::nav('close') ?></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-text-primary">Não-interesse</span>
                            <span class="mt-0.5 block text-xs text-text-secondary">A IA evita pautas ligadas a isto.</span>
                        </span>
                    </label>
                </div>
                <?php if (isset($errors['type'])): ?>
                    <p class="mt-1 text-sm text-danger"><?= View::e($errors['type']) ?></p>
                <?php endif; ?>
            </fieldset>

            <div class="mt-5">
                <?= Form::text('description', 'Descrição', $rule, $errors, required: true) ?>
                <p class="mt-1 text-xs text-text-muted">
                    Curta e direta — ex.: "receitas veganas" ou "conteúdo político partidário".
                </p>
            </div>

            <fieldset class="mt-5">
                <legend class="text-sm font-medium text-text-secondary">
                    Intensidade <span class="text-danger" aria-hidden="true">*</span>
                </legend>
                <div class="mt-2 grid grid-cols-5 gap-2">
                    <?php foreach ([1, 2, 3, 4, 5] as $n): ?>
                        <label class="flex cursor-pointer flex-col items-center gap-1 rounded-md border border-border bg-surface-2 py-2.5 text-text-primary transition-colors hover:border-border-strong has-[:checked]:border-cyan has-[:checked]:bg-cyan/10 has-[:checked]:text-cyan has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-cyan-bright has-[:focus-visible]:ring-offset-2 has-[:focus-visible]:ring-offset-surface">
                            <input type="radio" name="intensity" value="<?= $n ?>" <?= $intensity === $n ? 'checked' : '' ?> class="sr-only" data-intensity-radio>
                            <span class="font-mono text-base font-semibold"><?= $n ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="mt-2.5 flex items-center gap-2 text-xs text-text-secondary">
                    <span data-intensity-dots class="flex items-center gap-1" role="img" aria-label="Intensidade <?= $intensity ?> de 5">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="h-2 w-2 rounded-full <?= $i <= $intensity ? 'bg-cyan' : 'bg-border-strong' ?>"></span>
                        <?php endfor; ?>
                    </span>
                    <span data-intensity-label class="font-medium"><?= $intensityLabels[$intensity] ?></span>
                </p>
                <?php if (isset($errors['intensity'])): ?>
                    <p class="mt-1 text-sm text-danger"><?= View::e($errors['intensity']) ?></p>
                <?php endif; ?>
            </fieldset>
        </div>
    </div>

    <div class="mt-6 flex gap-3">
        <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
            <span class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav($isEdit ? 'check' : 'plus') ?></span>
            <?= $isEdit ? 'Salvar' : 'Adicionar' ?>
        </button>
        <a href="/sites/<?= View::e($site['id']) ?>/rules"
           class="btn btn-secondary px-4 py-2 text-sm">Cancelar</a>
    </div>
</form>

<script>
    // Progressive enhancement: o card e a tag do topo trocam de cor (ciano <->
    // âmbar) ao vivo conforme o tipo escolhido, e a legenda/pontinhos de
    // intensidade acompanham o radio marcado — tudo sem recarregar a página.
    // A validação de verdade continua no servidor; isto é só feedback visual.
    (function () {
        var card = document.getElementById('rule-card');
        var tag = document.getElementById('rule-type-tag');
        var typeRadios = document.querySelectorAll('[data-type-radio]');
        typeRadios.forEach(function (r) {
            r.addEventListener('change', function () {
                if (!r.checked || !card) return;
                card.style.setProperty('--tone', r.getAttribute('data-tone'));
                card.style.setProperty('--tint', r.getAttribute('data-tint'));
                if (!tag) return;
                var isNonInterest = r.value === 'NON_INTEREST';
                tag.textContent = isNonInterest ? 'Não-interesse' : 'Interesse';
                tag.classList.toggle('article-card-tag--warning', isNonInterest);
                tag.classList.toggle('article-card-tag--cyan', !isNonInterest);
            });
        });

        var labels = <?= json_encode($intensityLabels, JSON_UNESCAPED_UNICODE) ?>;
        var intensityRadios = document.querySelectorAll('[data-intensity-radio]');
        var labelOut = document.querySelector('[data-intensity-label]');
        var dotsOut = document.querySelector('[data-intensity-dots]');
        intensityRadios.forEach(function (r) {
            r.addEventListener('change', function () {
                if (!r.checked) return;
                var n = Number(r.value);
                if (labelOut) labelOut.textContent = labels[n] || '';
                if (dotsOut) {
                    dotsOut.setAttribute('aria-label', 'Intensidade ' + n + ' de 5');
                    dotsOut.querySelectorAll('span').forEach(function (dot, i) {
                        dot.classList.toggle('bg-cyan', i < n);
                        dot.classList.toggle('bg-border-strong', i >= n);
                    });
                }
            });
        });
    })();
</script>
