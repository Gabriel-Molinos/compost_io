<?php

declare(strict_types=1);

use App\View;

/** @var array<string,mixed> $site */
/** @var array<string,mixed> $article */
/** @var array<string,mixed>|null $version */
/** @var list<array<string,mixed>> $sources */
/** @var list<array<string,mixed>> $executions */
/** @var float $totalCost */
/** @var array<string,array<string,mixed>> $notes */

$activeTab = 'production';
require __DIR__ . '/../_tabs.php';
?>
<style>
    .article-body h2 { font-size: 1.25rem; font-weight: 700; margin: 1.5rem 0 .5rem; }
    .article-body h3 { font-size: 1.05rem; font-weight: 600; margin: 1.25rem 0 .5rem; }
    .article-body p { margin: .75rem 0; line-height: 1.7; }
    .article-body ul, .article-body ol { margin: .75rem 0; padding-left: 1.5rem; list-style: revert; }
    .article-body a { color: #0AFFEF; text-decoration: underline; }
    .article-body table { border-collapse: collapse; margin: 1rem 0; }
    .article-body th, .article-body td { border: 1px solid #2a3441; padding: .4rem .6rem; }
</style>
<a href="/sites/<?= View::e($site['id']) ?>/production" class="text-sm text-text-secondary hover:text-text-primary">← Produção</a>
<h2 class="mt-2 text-xl font-bold text-text-primary"><?= View::e($article['title'] ?: 'Rascunho #' . $article['id']) ?></h2>
<p class="mt-1 text-sm text-text-muted">
    Status: <?= View::e($article['status']) ?>
    <?php if (!empty($article['focus_keyword'])): ?> · palavra-chave: <em><?= View::e($article['focus_keyword']) ?></em><?php endif; ?>
    · custo total ~US$ <?= number_format($totalCost, 4) ?>
</p>
<?php if (!empty($article['meta_description'])): ?>
    <p class="mt-2 text-sm text-text-secondary"><strong>Meta descrição:</strong> <?= View::e($article['meta_description']) ?></p>
<?php endif; ?>

<section class="mt-6">
    <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Passos da IA</h3>
    <ul class="mt-2 divide-y divide-border rounded-lg border border-border text-sm">
        <?php foreach ($executions as $e): ?>
            <li class="flex items-center justify-between gap-4 px-4 py-2">
                <span class="text-text-primary"><?= View::e($e['step']) ?></span>
                <span class="flex items-center gap-3 text-xs text-text-muted">
                    <?php if ((int) $e['retry_count'] > 0): ?><span><?= View::e($e['retry_count']) ?> retry</span><?php endif; ?>
                    <span>US$ <?= number_format((float) $e['cost'], 4) ?></span>
                    <span class="<?= $e['status'] === 'SUCCESS' ? 'text-success' : ($e['status'] === 'FAILED' ? 'text-danger' : 'text-text-muted') ?>">
                        <?= View::e($e['status']) ?>
                    </span>
                </span>
            </li>
            <?php if (!empty($e['error_message'])): ?>
                <li class="px-4 py-2 text-xs text-danger"><?= View::e($e['error_message']) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
</section>

<?php if ($sources !== []): ?>
    <section class="mt-6">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Fontes (<?= count($sources) ?>)</h3>
        <ul class="mt-2 space-y-1 text-sm">
            <?php foreach ($sources as $s): ?>
                <li>
                    <a href="<?= View::e($s['url']) ?>" target="_blank" rel="noopener"
                       class="text-cyan hover:text-cyan-light"><?= View::e($s['title'] ?: $s['url']) ?></a>
                    <?php if (!empty($s['publisher'])): ?><span class="text-text-muted"> — <?= View::e($s['publisher']) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php
$review = $notes['review'] ?? null;
$seo = $notes['seo'] ?? null;
$compliance = $notes['compliance'] ?? null;
?>
<?php if ($review !== null): ?>
    <section class="mt-6 rounded-lg border border-border bg-surface p-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Parecer da IA (pré-revisão humana)</h3>
        <p class="mt-2 text-sm text-text-primary">
            <strong><?= View::e($review['recommendation'] ?? '?') ?></strong> — <?= View::e($review['summary'] ?? '') ?>
        </p>
        <?php if (!empty($review['concerns'])): ?>
            <ul class="mt-2 list-disc pl-5 text-sm text-text-secondary">
                <?php foreach ($review['concerns'] as $c): ?>
                    <li><?php if (!empty($c['area'])): ?><span class="text-text-muted">[<?= View::e($c['area']) ?>]</span> <?php endif; ?><?= View::e($c['note'] ?? '') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($review['assumptions_made'])): ?>
            <p class="mt-2 text-xs text-text-muted">Suposições da IA: <?= View::e(implode(' · ', $review['assumptions_made'])) ?></p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php
$gate = static function (string $label, ?array $note, bool $ok, array $items) : void {
    if ($note === null) { return; }
    echo '<div class="rounded-lg border border-border bg-surface p-4">';
    echo '<p class="text-sm"><span class="font-semibold text-text-primary">' . View::e($label) . '</span> — '
        . ($ok ? '<span class="text-success">ok</span>' : '<span class="text-danger">com pendências</span>') . '</p>';
    if ($items !== []) {
        echo '<ul class="mt-2 list-disc pl-5 text-sm text-text-secondary">';
        foreach ($items as $it) { echo '<li>' . View::e($it) . '</li>'; }
        echo '</ul>';
    }
    echo '</div>';
};
$seoItems = [];
foreach ((array) ($seo['issues'] ?? []) as $i) {
    $seoItems[] = '[' . ($i['severity'] ?? '?') . '] ' . ($i['item'] ?? '') . (empty($i['fix']) ? '' : ' → ' . $i['fix']);
}
$compItems = [];
foreach ((array) ($compliance['blocking'] ?? []) as $b) {
    $compItems[] = ($b['rule'] ?? '') . (empty($b['fix']) ? '' : ' → ' . $b['fix']);
}
foreach ((array) ($compliance['warnings'] ?? []) as $w) {
    $compItems[] = '(aviso) ' . ($w['rule'] ?? '') . (empty($w['note']) ? '' : ': ' . $w['note']);
}
?>
<?php if ($seo !== null || $compliance !== null): ?>
    <section class="mt-4 grid gap-3 sm:grid-cols-2">
        <?php $gate('SEO', $seo, (bool) ($seo['passes'] ?? false), $seoItems); ?>
        <?php $gate('Compliance', $compliance, (bool) ($compliance['approved'] ?? false), $compItems); ?>
    </section>
<?php endif; ?>

<section class="mt-8">
    <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">
        Corpo <?php if ($version && $version['word_count']): ?><span class="text-text-muted">(<?= View::e($version['word_count']) ?> palavras)</span><?php endif; ?>
    </h3>
    <?php if ($version === null): ?>
        <p class="mt-2 text-text-secondary">Ainda sem corpo — a escrita não chegou a rodar.</p>
    <?php else: ?>
        <article class="article-body mt-3 max-w-none rounded-lg border border-border bg-surface p-6 text-text-primary">
            <?= $version['content'] /* HTML gerado pela IA — renderizado como veio */ ?>
        </article>
    <?php endif; ?>
</section>
