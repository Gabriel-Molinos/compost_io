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
/** @var list<array{domain: string, filename: string, url: string}> $logoLibrary */

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

    <section class="rounded-lg border border-border bg-surface p-5" data-avatar-scope>
        <h2 class="font-display text-base font-semibold text-text-primary">Identidade</h2>

        <div class="mt-4 flex items-center gap-4">
            <span data-avatar-preview data-avatar-img-class="<?= View::e(Avatar::imgClass('h-16 w-16', 'rounded-lg', 'cover', 'bg-white')) ?>">
                <?= Avatar::html($site['logo_path'] ?? null, $site['name'] ?? '?', size: 'h-16 w-16', textSize: 'text-xl', bg: 'bg-white') ?>
            </span>
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

        <?php if ($logoLibrary !== []): ?>
            <details class="mt-4">
                <summary class="cursor-pointer text-sm font-medium text-cyan hover:text-cyan-bright">
                    Ou escolher uma logo pronta da biblioteca (<?= count($logoLibrary) ?>)
                </summary>
                <p class="mt-2 text-xs text-text-muted">
                    Cada domínio tem no máximo uma logo aqui. Escolher uma abaixo substitui o upload acima
                    (se os dois forem enviados juntos, o upload manual tem prioridade).
                </p>
                <div class="mt-3 grid max-h-80 grid-cols-4 gap-2 overflow-y-auto rounded-md border border-border bg-surface-2/40 p-3 sm:grid-cols-6">
                    <?php foreach ($logoLibrary as $l): ?>
                        <label class="group flex cursor-pointer flex-col items-center gap-1 rounded-md border border-transparent p-1.5 has-[:checked]:border-cyan has-[:checked]:bg-cyan/10">
                            <input type="radio" name="library_logo" value="<?= View::e($l['filename']) ?>" class="sr-only">
                            <img src="<?= View::e($l['url']) ?>" alt="" loading="lazy"
                                 class="h-12 w-12 rounded bg-white object-contain p-1">
                            <span class="w-full truncate text-center text-[10px] text-text-muted group-has-[:checked]:text-cyan" title="<?= View::e($l['domain']) ?>">
                                <?= View::e($l['domain']) ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>

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

<?php if ($isEdit): ?>
    <section class="mt-8 max-w-xl rounded-lg border border-danger/40 bg-danger/5 p-5">
        <h2 class="font-display text-base font-semibold text-danger">Zona de perigo</h2>
        <p class="mt-1 text-sm text-text-secondary">
            Exclui o site e <strong>tudo</strong> que está ligado a ele — artigos, categorias, metas,
            histórico de custo de IA, conexão com o WordPress. Não tem como desfazer.
        </p>
        <form method="post" action="/sites/<?= View::e($site['id']) ?>/delete" class="mt-4"
              data-confirm="Excluir &quot;<?= View::e($site['name']) ?>&quot; de vez? Não tem como desfazer.">
            <?= Csrf::field() ?>
            <label class="block text-sm">
                <span class="text-text-secondary">Digite <strong class="text-text-primary"><?= View::e($site['name']) ?></strong> pra confirmar</span>
                <input type="text" name="confirm_name" required autocomplete="off"
                       data-delete-confirm-input="<?= View::e($site['name']) ?>"
                       class="mt-1.5 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-danger focus:outline-none">
            </label>
            <button type="submit" disabled data-delete-confirm-button
                    class="mt-3 rounded-md border border-danger/50 px-4 py-2 text-sm font-semibold text-danger transition-colors hover:bg-danger/10 disabled:cursor-not-allowed disabled:opacity-40">
                Excluir site permanentemente
            </button>
        </form>
    </section>

    <script>
        // Progressive enhancement: botão só habilita com o nome exato digitado
        // — o servidor confere de novo (SiteController::destroy() nunca confia só no JS).
        (function () {
            var input = document.querySelector('[data-delete-confirm-input]');
            var button = document.querySelector('[data-delete-confirm-button]');
            if (!input || !button) return;
            input.addEventListener('input', function () {
                button.disabled = input.value !== input.getAttribute('data-delete-confirm-input');
            });
        })();
    </script>
<?php endif; ?>
