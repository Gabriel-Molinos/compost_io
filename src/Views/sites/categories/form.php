<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Form;
use App\View;

/** @var array<string, mixed> $site */
/** @var array<string, mixed> $category */
/** @var string $action */
/** @var array<string, string> $errors */

$activeTab = 'categories';
require __DIR__ . '/../_tabs.php';

$isEdit = !empty($category['id']);
?>
<a href="/sites/<?= View::e($site['id']) ?>/categories" class="text-sm text-text-secondary hover:text-text-primary">
    ← Categorias
</a>
<h2 class="font-display mt-2 text-lg font-semibold text-text-primary"><?= $isEdit ? 'Editar categoria' : 'Nova categoria' ?></h2>

<form method="post" action="<?= View::e($action) ?>" class="mt-6 max-w-xl space-y-5" novalidate>
    <?= Csrf::field() ?>
    <?= Form::text('name', 'Nome', $category, $errors, required: true) ?>
    <?= Form::textarea('guidelines', 'Diretrizes da categoria', $category, $errors, rows: 4) ?>
    <p class="text-xs text-text-muted">
        As diretrizes orientam a IA ao produzir artigos desta categoria (ex.: “focar iniciantes, usar comparativos”).
    </p>

    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-md bg-cyan px-4 py-2 font-semibold text-[#050B0F] hover:bg-cyan-bright">
            <?= $isEdit ? 'Salvar' : 'Criar categoria' ?>
        </button>
        <a href="/sites/<?= View::e($site['id']) ?>/categories"
           class="rounded-md border border-border px-4 py-2 text-text-secondary hover:text-text-primary">Cancelar</a>
    </div>
</form>
