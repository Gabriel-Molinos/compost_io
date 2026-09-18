<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\View;

/** @var array<string,mixed> $site */
/** @var list<string> $steps */
/** @var list<array<string,mixed>> $goals */
/** @var list<array<string,mixed>> $categories */
/** @var array{step:string,goal_id:string,category_id:string} $input */
/** @var string|null $prompt */
/** @var array<string,mixed>|null $result */
/** @var string|null $error */

$activeTab = 'ai';
require __DIR__ . '/../_tabs.php';
?>
<p class="text-sm text-text-secondary">
    Monta o prompt em camadas (Prompt Base + passo + identidade do site + meta +
    categoria) e envia ao Gemini. <strong>Cada execução é uma chamada real e tem custo.</strong>
    Nada é gravado — a produção de artigos de verdade é a próxima fatia da Fase 4.
</p>

<form method="post" action="/sites/<?= View::e($site['id']) ?>/ai-playground" class="mt-6 grid gap-4 sm:grid-cols-4">
    <?= Csrf::field() ?>

    <label class="text-sm">
        <span class="block font-medium text-text-secondary">Passo</span>
        <select name="step" class="mt-1 w-full">
            <?php foreach ($steps as $s): ?>
                <option value="<?= $s ?>" <?= $input['step'] === $s ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="text-sm">
        <span class="block font-medium text-text-secondary">Meta (opcional)</span>
        <select name="goal_id" class="mt-1 w-full">
            <option value="">—</option>
            <?php foreach ($goals as $g): ?>
                <option value="<?= View::e($g['id']) ?>" <?= $input['goal_id'] === (string) $g['id'] ? 'selected' : '' ?>>
                    <?= View::e($g['period']) ?> (<?= View::e($g['total_articles']) ?> art.)
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="text-sm">
        <span class="block font-medium text-text-secondary">Categoria (opcional)</span>
        <select name="category_id" class="mt-1 w-full">
            <option value="">—</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= View::e($c['id']) ?>" <?= $input['category_id'] === (string) $c['id'] ? 'selected' : '' ?>>
                    <?= View::e($c['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <div class="flex items-end">
        <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
            Rodar
        </button>
    </div>
</form>

<?php if ($error !== null): ?>
    <p role="alert" class="mt-6 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        <?= View::e($error) ?>
    </p>
<?php endif; ?>

<?php if ($result !== null): ?>
    <section class="mt-8">
        <h2 class="font-display text-lg font-semibold text-text-primary">Resposta do Gemini</h2>
        <p class="mt-1 text-xs text-text-muted">
            <?= View::e($result['model']) ?> · <?= View::e($result['elapsed']) ?>s ·
            tokens: prompt <?= View::e($result['tokens']['prompt']) ?> ·
            raciocínio <?= View::e($result['tokens']['thoughts']) ?> ·
            resposta <?= View::e($result['tokens']['output']) ?> ·
            total <?= View::e($result['tokens']['total']) ?>
        </p>
        <pre class="mt-3 overflow-x-auto rounded-lg border border-border bg-surface p-4 text-sm text-text-primary whitespace-pre-wrap"><?= View::e($result['text']) ?></pre>
    </section>
<?php endif; ?>

<?php if ($prompt !== null): ?>
    <section class="mt-8">
        <details>
            <summary class="cursor-pointer text-sm font-semibold text-text-secondary hover:text-text-primary">
                Ver o prompt enviado (<?= number_format(mb_strlen($prompt)) ?> caracteres)
            </summary>
            <pre class="mt-3 overflow-x-auto rounded-lg border border-border bg-surface p-4 text-xs text-text-muted whitespace-pre-wrap"><?= View::e($prompt) ?></pre>
        </details>
    </section>
<?php endif; ?>
