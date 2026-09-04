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

<?php if ($errors !== []): ?>
    <p role="alert" class="mt-4 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        Corrija os campos destacados abaixo.
    </p>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" class="mt-5 max-w-xl" novalidate>
    <?= Csrf::field() ?>

    <section class="rounded-lg border border-border bg-surface p-5">
        <?= Form::text('name', 'Nome', $category, $errors, required: true) ?>

        <div class="mt-5">
            <?= Form::textarea('guidelines', 'Diretrizes da categoria', $category, $errors, rows: 5) ?>
            <p class="mt-1 text-xs text-text-muted">
                Orientam a IA ao produzir artigos desta categoria (ex.: "focar iniciantes, usar comparativos").
                Deixe em branco pra IA decidir o tom sozinha.
            </p>
        </div>
    </section>

    <div class="mt-6 flex gap-3">
        <button type="submit" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            <?= $isEdit ? 'Salvar' : 'Criar categoria' ?>
        </button>
        <a href="/sites/<?= View::e($site['id']) ?>/categories"
           class="rounded-md border border-border px-4 py-2 text-sm text-text-secondary hover:border-border-strong hover:text-text-primary">Cancelar</a>
    </div>
</form>
