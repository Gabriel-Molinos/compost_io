<?php

declare(strict_types=1);

use App\Support\Avatar;
use App\Support\Csrf;
use App\Support\Form;
use App\Support\Icon;
use App\Support\Labels;
use App\View;

/** @var array<string, mixed> $site */
/** @var string $action */
/** @var array<string, string> $errors */

$isEdit = !empty($site['id']);
$backHref = $isEdit ? '/sites/' . $site['id'] : '/sites';

if ($isEdit) {
    $activeTab = 'config';
    require __DIR__ . '/_tabs.php';
}
?>
<a href="<?= View::e($backHref) ?>" class="text-sm text-text-secondary hover:text-text-primary">← <?= $isEdit ? View::e($site['name']) : 'Sites' ?></a>

<div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div class="flex items-start gap-3">
        <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan"><?= Icon::nav('config') ?></span>
        <div>
            <h1 class="font-display text-2xl font-bold text-text-primary"><?= $isEdit ? 'Configuração do site' : 'Novo site' ?></h1>
            <p class="mt-1 text-sm text-text-secondary">Identidade e voz editorial — a IA usa isso em toda geração de conteúdo.</p>
        </div>
    </div>
    <?php if ($isEdit): ?>
        <?= Labels::activeBadge(!empty($site['is_active'])) ?>
    <?php endif; ?>
</div>

<?php if ($errors !== []): ?>
    <p role="alert" class="mt-4 rounded-md border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
        Corrija os campos destacados abaixo.
    </p>
<?php endif; ?>

<form method="post" action="<?= View::e($action) ?>" enctype="multipart/form-data" class="mt-6 max-w-xl" novalidate>
    <?= Csrf::field() ?>

    <section class="rounded-lg border border-border bg-surface p-5">
        <h2 class="font-display text-base font-semibold text-text-primary">Identidade</h2>

        <div class="mt-4 flex items-center gap-4">
            <?= Avatar::html($site['logo_path'] ?? null, $site['name'] ?? '?', size: 'h-16 w-16', textSize: 'text-xl', bg: 'bg-white') ?>
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

        <div class="mt-5 space-y-5">
            <?= Form::text('name', 'Nome', $site, $errors, required: true) ?>
            <?= Form::text('niche', 'Nicho', $site, $errors) ?>
        </div>
    </section>

    <section class="mt-6 rounded-lg border border-border bg-surface p-5">
        <h2 class="font-display text-base font-semibold text-text-primary">Voz editorial</h2>
        <p class="mt-1 text-sm text-text-secondary">Orienta a IA na produção — quanto mais específico, mais os artigos soam com a cara do site.</p>

        <div class="mt-4 space-y-5">
            <div class="grid grid-cols-2 gap-4">
                <?= Form::text('language', 'Idioma', $site, $errors, required: true) ?>
                <?= Form::text('tone', 'Tom', $site, $errors) ?>
            </div>

            <?= Form::text('target_audience', 'Público', $site, $errors) ?>

            <div>
                <?= Form::textarea('editorial_identity', 'Identidade editorial', $site, $errors, rows: 4) ?>
                <p class="mt-1 text-xs text-text-muted">
                    Voz e estilo do site em texto livre (ex.: “explica como para um amigo, usa exemplos reais, evita jargão”).
                </p>
            </div>
        </div>
    </section>

    <section class="mt-6 rounded-lg border border-border bg-surface p-5">
        <h2 class="font-display text-base font-semibold text-text-primary">Publicação</h2>

        <div class="mt-4 space-y-5">
            <?= Form::text('wordpress_url', 'URL do WordPress', $site, $errors, type: 'url') ?>
            <?= Form::checkbox('is_active', 'Site ativo', $site, default: true) ?>
        </div>
    </section>

    <div class="mt-6 flex gap-3">
        <button type="submit"
                class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-bright">
            <?= $isEdit ? 'Salvar' : 'Criar site' ?>
        </button>
        <a href="<?= View::e($backHref) ?>" class="rounded-md border border-border px-4 py-2 text-sm text-text-secondary hover:border-border-strong hover:text-text-primary">
            Cancelar
        </a>
    </div>
</form>
