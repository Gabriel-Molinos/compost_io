<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Form;
use App\View;

/** @var array<string, mixed> $site */
/** @var string $action */
/** @var array<string, string> $errors */

$isEdit = !empty($site['id']);
$backHref = $isEdit ? '/sites/' . $site['id'] : '/sites';

if ($isEdit) {
    $activeTab = 'config';
    require __DIR__ . '/_tabs.php';
} else {
    ?>
    <div class="flex items-center gap-3">
        <a href="/sites" class="text-sm text-text-secondary hover:text-text-primary">← Sites</a>
    </div>
    <h1 class="font-display mt-2 text-2xl font-bold text-text-primary">Novo site</h1>
    <?php
}

if ($isEdit): ?>
    <h2 class="font-display text-lg font-semibold text-text-primary">Configuração do site</h2>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <p role="alert" class="mt-4 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        Corrija os campos destacados abaixo.
    </p>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" enctype="multipart/form-data" class="mt-6 max-w-xl space-y-5" novalidate>
    <?= Csrf::field() ?>

    <div class="flex items-center gap-4">
        <?= Avatar::html($site['logo_path'] ?? null, $site['name'] ?? '?', size: 'h-16 w-16', textSize: 'text-xl') ?>
        <div class="flex-1">
            <?= Form::file('logo', 'Logo do site', $errors['logo'] ?? null) ?>
            <?php if ($isEdit && !empty($site['logo_path'])): ?>
                <label class="mt-2 flex items-center gap-2 text-sm text-text-secondary">
                    <input type="checkbox" name="remove_logo" value="1"
                           class="h-4 w-4 rounded border-border bg-surface-2 text-cyan focus:ring-cyan">
                    Remover logo atual
                </label>
            <?php endif; ?>
        </div>
    </div>

    <?= Form::text('name', 'Nome', $site, $errors, required: true) ?>
    <?= Form::text('niche', 'Nicho', $site, $errors) ?>

    <div class="grid grid-cols-2 gap-4">
        <?= Form::text('language', 'Idioma', $site, $errors, required: true) ?>
        <?= Form::text('tone', 'Tom', $site, $errors) ?>
    </div>

    <?= Form::text('target_audience', 'Público', $site, $errors) ?>

    <?= Form::textarea('editorial_identity', 'Identidade editorial', $site, $errors, rows: 4) ?>
    <p class="-mt-3 text-xs text-text-muted">
        Voz e estilo do site em texto livre — orienta a IA na produção (ex.: “explica como para um amigo, usa exemplos reais, evita jargão”).
    </p>

    <?= Form::text('wordpress_url', 'URL do WordPress', $site, $errors, type: 'url') ?>
    <?= Form::checkbox('is_active', 'Site ativo', $site, default: true) ?>

    <div class="flex gap-3 pt-2">
        <button type="submit"
                class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            <?= $isEdit ? 'Salvar' : 'Criar site' ?>
        </button>
        <a href="<?= View::e($backHref) ?>" class="rounded-md border border-border px-4 py-2 text-sm text-text-secondary hover:border-border-strong hover:text-text-primary">
            Cancelar
        </a>
    </div>
</form>
