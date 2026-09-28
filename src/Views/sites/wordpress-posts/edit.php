<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Icon;
use App\Support\ImageUploadValidator;
use App\View;

/** @var array<string, mixed> $site */
/** @var array<string, mixed> $row espelho local (wordpress_posts_mirror) */
/** @var bool $isCompost */
/** @var string $content */
/** @var string $excerptRaw */
/** @var string $titleRaw */
/** @var int $authorId */
/** @var list<int> $categoryIds */
/** @var list<array<string, mixed>> $categories */
/** @var list<array<string, mixed>> $authors */
/** @var string|null $featuredUrl */

$activeTab = 'wpposts';
require __DIR__ . '/../_tabs.php';

$back = '/sites/' . View::e($site['id']) . '/wordpress-posts';
$action = $back . '/' . View::e($row['id']);
$currentCategoryId = $categoryIds[0] ?? 0;
$upMinW = ImageUploadValidator::MIN_WIDTH;
$upMaxW = ImageUploadValidator::MAX_WIDTH;
$upRatio = ImageUploadValidator::RATIO_W . ':' . ImageUploadValidator::RATIO_H;
$upMb = ImageUploadValidator::MAX_BYTES / 1048576;
?>
<div class="flex items-start gap-3">
    <a href="<?= $back ?>" class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-border text-text-secondary hover:border-cyan hover:text-cyan" aria-label="Voltar pra Todos os posts">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
    </a>
    <div>
        <h1 class="font-display text-2xl font-bold text-text-primary">Editar post</h1>
        <p class="mt-1 max-w-xl text-sm text-text-secondary">
            Salvar aqui manda a mudança direto pro WordPress — sem precisar abrir o wp-admin.
        </p>
    </div>
</div>

<?php if (!$isCompost): ?>
    <div class="mt-4 flex items-start gap-3 rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm text-text-primary">
        <span class="mt-0.5 shrink-0 text-warning [&>svg]:h-5 [&>svg]:w-5"><?= Icon::nav('alert') ?></span>
        <p>
            Este post foi criado <strong>direto no WordPress</strong>, não pelo COMPOST — provavelmente usa o editor
            de blocos (Gutenberg) de lá. Salvar o corpo por aqui funciona, mas troca o conteúdo por HTML simples:
            o post continua igual pra quem lê, mas deixa de ser editável em blocos se alguém abrir de novo no
            WordPress. Título, resumo, categoria, autor e imagem destacada podem ser trocados sem esse efeito.
        </p>
    </div>
<?php endif; ?>

<form method="post" action="<?= $action ?>" enctype="multipart/form-data" class="mt-6 space-y-6">
    <?= Csrf::field() ?>

    <section class="rounded-xl border border-border bg-surface p-5">
        <label class="block text-sm font-medium text-text-secondary">
            Título
            <input type="text" name="title" required value="<?= View::e($titleRaw) ?>"
                   class="mt-2 w-full rounded-md border border-border bg-surface-2 px-4 py-2.5 text-text-primary focus:border-cyan focus:outline-none">
        </label>

        <label class="mt-4 block text-sm font-medium text-text-secondary">
            Resumo (excerpt)
            <textarea name="excerpt" rows="2"
                      class="mt-2 w-full rounded-md border border-border bg-surface-2 px-4 py-2.5 text-text-primary focus:border-cyan focus:outline-none"><?= View::e($excerptRaw) ?></textarea>
        </label>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <label class="block text-sm font-medium text-text-secondary">
                Categoria
                <select name="category_id" class="mt-2 w-full rounded-md border border-border bg-surface-2 px-3 py-2.5 text-text-primary focus:border-cyan focus:outline-none">
                    <option value="0">— sem trocar —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= View::e($c['id']) ?>" <?= (int) $c['id'] === $currentCategoryId ? 'selected' : '' ?>>
                            <?= View::e(html_entity_decode((string) ($c['name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="block text-sm font-medium text-text-secondary">
                Autor
                <select name="author_id" class="mt-2 w-full rounded-md border border-border bg-surface-2 px-3 py-2.5 text-text-primary focus:border-cyan focus:outline-none">
                    <option value="0">— sem trocar —</option>
                    <?php foreach ($authors as $a): ?>
                        <option value="<?= View::e($a['id']) ?>" <?= (int) $a['id'] === $authorId ? 'selected' : '' ?>>
                            <?= View::e((string) ($a['name'] ?? ('Autor #' . $a['id']))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </section>

    <section class="rounded-xl border border-border bg-surface p-5">
        <p class="text-sm font-semibold text-text-primary">Imagem destacada</p>
        <p class="mt-1 text-xs text-text-muted">Só troca se você enviar uma nova abaixo — sem geração por IA aqui, é upload direto.</p>
        <div class="mt-3 flex flex-wrap items-start gap-4">
            <?php if ($featuredUrl !== null): ?>
                <img src="<?= View::e($featuredUrl) ?>" alt="" class="h-28 w-28 shrink-0 rounded-lg border border-border object-cover">
            <?php else: ?>
                <span class="flex h-28 w-28 shrink-0 items-center justify-center rounded-lg border border-dashed border-border text-text-muted [&>svg]:h-7 [&>svg]:w-7"><?= Icon::nav('camera') ?></span>
            <?php endif; ?>
            <div class="min-w-[16rem] flex-1">
                <input type="file" name="featured_image" accept="image/webp"
                       class="block w-full text-sm text-text-secondary file:mr-3 file:rounded-md file:border-0 file:bg-cyan/15 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-cyan hover:file:bg-cyan/25">
                <p class="mt-2 text-xs text-text-muted">
                    WebP, largura de <?= $upMinW ?> a <?= $upMaxW ?> px, proporção <?= $upRatio ?>, até <?= $upMb ?> MB.
                </p>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-border bg-surface p-5">
        <label class="text-sm font-medium text-text-secondary">
            Corpo do post
            <textarea name="content" id="content-html-editor" required
                      class="mt-2 w-full rounded-md border border-border bg-surface-2 px-4 py-3 text-sm text-text-primary"><?= View::e($content) ?></textarea>
        </label>
    </section>

    <div class="flex items-center gap-3">
        <button type="submit" class="btn btn-primary px-5 py-2.5 text-sm">Salvar no WordPress</button>
        <a href="<?= $back ?>" class="text-sm text-text-secondary hover:text-text-primary">Cancelar</a>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin" defer></script>
<script src="https://cdn.jsdelivr.net/npm/tinymce-i18n@24/langs6/pt_BR.js" referrerpolicy="origin" defer></script>
<script src="<?= View::e(View::asset('assets/js/rich-editor.js')) ?>" defer></script>
