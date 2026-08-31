<?php

declare(strict_types=1);

use App\Services\ArticleReviewService;
use App\Support\Csrf;
use App\Support\Labels;
use App\View;

/** @var array<string,mixed> $site */
/** @var array<string,mixed> $article */
/** @var array<string,mixed>|null $version */
/** @var list<array<string,mixed>> $sources */
/** @var list<array<string,mixed>> $executions */
/** @var float $totalCost */
/** @var array<string,array<string,mixed>> $notes */
/** @var list<array<string,mixed>> $images */
/** @var list<array<string,mixed>> $feedback */
/** @var array<string,mixed>|null $schedule */
/** @var list<array<string,mixed>> $authors */

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
    Status: <strong class="text-text-secondary"><?= View::e(Labels::articleStatus($article['status'])) ?></strong>
    <?php if ((int) ($article['attempt_number'] ?? 1) > 1): ?> · tentativa <?= View::e($article['attempt_number']) ?><?php endif; ?>
    <?php if (!empty($article['focus_keyword'])): ?> · palavra-chave: <em><?= View::e($article['focus_keyword']) ?></em><?php endif; ?>
    · custo total ~US$ <?= number_format($totalCost, 4) ?>
</p>
<?php if (!empty($article['meta_description'])): ?>
    <p class="mt-2 text-sm text-text-secondary"><strong>Meta descrição:</strong> <?= View::e($article['meta_description']) ?></p>
<?php endif; ?>

<?php if ($article['status'] === 'IN_REVIEW'): ?>
    <section class="mt-5 rounded-lg border border-border bg-surface p-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Revisão</h3>
        <p class="mt-1 text-sm text-text-secondary">Aprovar libera o agendamento. Rejeitar registra o motivo e permite regenerar.</p>
        <div class="mt-3 flex flex-wrap items-start gap-6">
            <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($article['id']) ?>/approve">
                <?= Csrf::field() ?>
                <button type="submit" class="rounded-md bg-success/90 px-4 py-2 text-sm font-semibold text-[#04210F] hover:bg-success">
                    Aprovar
                </button>
            </form>
            <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($article['id']) ?>/reject"
                  class="flex flex-1 flex-col gap-2 sm:min-w-[20rem]"
                  onsubmit="return this.justification.value.trim() !== '' || (alert('Escreva a justificativa.'), false);">
                <?= Csrf::field() ?>
                <label class="text-sm">
                    <span class="block font-medium text-text-secondary">Motivo da rejeição</span>
                    <select name="reason" required
                            class="mt-1 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-cyan focus:outline-none">
                        <?php foreach (ArticleReviewService::REJECT_REASONS as $value => $label): ?>
                            <option value="<?= View::e($value) ?>"><?= View::e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="text-sm">
                    <span class="block font-medium text-text-secondary">Justificativa</span>
                    <textarea name="justification" rows="3" required
                              class="mt-1 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-cyan focus:outline-none"
                              placeholder="O que está errado e o que a regeneração precisa corrigir."></textarea>
                </label>
                <button type="submit" class="self-start rounded-md border border-danger/50 px-4 py-2 text-sm font-semibold text-danger hover:bg-danger/10">
                    Rejeitar
                </button>
            </form>
        </div>
    </section>
<?php endif; ?>

<?php
$featuredSelected = null;
foreach ($images as $img) {
    if ($img['role'] === 'FEATURED' && (int) $img['selected'] === 1) {
        $featuredSelected = $img;
        break;
    }
}
$scheduleBase = '/sites/' . View::e($site['id']) . '/production/' . View::e($article['id']);
$nowLocal = date('Y-m-d\TH:i');

$authorOptions = static function (array $authors, int $selectedId): string {
    $html = '';
    foreach ($authors as $a) {
        $sel = (int) $a['id'] === $selectedId ? ' selected' : '';
        $html .= '<option value="' . View::e($a['id']) . '"' . $sel . '>' . View::e($a['name']) . '</option>';
    }
    return $html;
};
?>

<?php if ($article['status'] === 'APPROVED'): ?>
    <section id="agendar" class="mt-5 rounded-lg border border-border bg-surface p-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Agendar publicação</h3>
        <?php if ($authors === []): ?>
            <p class="mt-1 text-sm text-text-secondary">
                Nenhum autor disponível. Sincronize os autores em
                <a href="/sites/<?= View::e($site['id']) ?>/wordpress" class="text-cyan hover:text-cyan-light">WordPress</a>.
            </p>
        <?php elseif ($featuredSelected === null): ?>
            <p class="mt-1 text-sm text-text-secondary">
                Escolha a <a href="#imagens" class="text-cyan hover:text-cyan-light">imagem destacada</a> antes de agendar.
            </p>
        <?php else: ?>
            <p class="mt-1 text-sm text-text-secondary">Define autor, data/hora e usa a imagem destacada já escolhida. O envio ao WordPress é um passo à parte.</p>
            <form method="post" action="<?= $scheduleBase ?>/schedule" class="mt-3 grid gap-4 sm:max-w-md">
                <?= Csrf::field() ?>
                <input type="hidden" name="image_id" value="<?= View::e($featuredSelected['id']) ?>">
                <label class="text-sm">
                    <span class="block font-medium text-text-secondary">Autor</span>
                    <select name="author_id" required
                            class="mt-1 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-cyan focus:outline-none">
                        <?= $authorOptions($authors, 0) ?>
                    </select>
                </label>
                <label class="text-sm">
                    <span class="block font-medium text-text-secondary">Data e hora da publicação</span>
                    <input type="datetime-local" name="scheduled_date" required min="<?= $nowLocal ?>"
                           class="mt-1 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-cyan focus:outline-none">
                </label>
                <button type="submit" class="justify-self-start rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-light">
                    Agendar
                </button>
            </form>
        <?php endif; ?>
    </section>
<?php elseif ($article['status'] === 'SCHEDULED' && $schedule !== null): ?>
    <?php $imageIdForForm = $featuredSelected['id'] ?? $schedule['image_id']; ?>
    <section id="agendar" class="mt-5 rounded-lg border border-cyan/40 bg-surface p-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Agendado</h3>
        <p class="mt-1 text-sm text-text-primary">
            <strong><?= View::e(date('d/m/Y H:i', strtotime((string) $schedule['scheduled_date']))) ?></strong>
            · autor: <?= View::e($schedule['author_name'] ?? '—') ?>
        </p>
        <?php if (!empty($schedule['image_url'])): ?>
            <img src="<?= View::e($schedule['image_url']) ?>" alt="" class="mt-2 w-40 rounded border border-border">
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap items-start gap-6">
            <details class="text-sm">
                <summary class="cursor-pointer font-medium text-cyan hover:text-cyan-light">Reagendar</summary>
                <form method="post" action="<?= $scheduleBase ?>/schedule/update" class="mt-3 grid gap-4 sm:max-w-md">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="image_id" value="<?= View::e($imageIdForForm) ?>">
                    <label class="text-sm">
                        <span class="block font-medium text-text-secondary">Autor</span>
                        <select name="author_id" required
                                class="mt-1 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-cyan focus:outline-none">
                            <?= $authorOptions($authors, (int) $schedule['author_id']) ?>
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="block font-medium text-text-secondary">Data e hora</span>
                        <input type="datetime-local" name="scheduled_date" required min="<?= $nowLocal ?>"
                               value="<?= View::e(date('Y-m-d\TH:i', strtotime((string) $schedule['scheduled_date']))) ?>"
                               class="mt-1 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-cyan focus:outline-none">
                    </label>
                    <button type="submit" class="justify-self-start rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-light">
                        Salvar
                    </button>
                </form>
            </details>
            <form method="post" action="<?= $scheduleBase ?>/schedule/cancel"
                  onsubmit="return confirm('Cancelar o agendamento? O artigo volta para aprovado.');">
                <?= Csrf::field() ?>
                <button type="submit" class="rounded-md border border-danger/50 px-4 py-2 text-sm font-semibold text-danger hover:bg-danger/10">
                    Cancelar agendamento
                </button>
            </form>
        </div>
    </section>
<?php endif; ?>

<?php
$attempt = (int) ($article['attempt_number'] ?? 1);
$maxAttempts = 3;
?>
<?php if ($article['status'] === 'REVISION_REQUESTED'): ?>
    <section class="mt-5 rounded-lg border border-border bg-surface p-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Regeneração</h3>
        <p class="mt-1 text-sm text-text-secondary">
            Tentativa <?= $attempt ?> de <?= $maxAttempts ?>.
            <?php if ($attempt >= $maxAttempts): ?>
                Esta é a última — se a próxima for rejeitada, o artigo fica <strong>bloqueado</strong> para decisão sua.
            <?php else: ?>
                A IA recebe o motivo da rejeição e refaz o artigo (pesquisa, texto, SEO, imagens). Leva alguns minutos e tem custo.
            <?php endif; ?>
        </p>
        <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($article['id']) ?>/regenerate"
              class="mt-3"
              onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Regenerando… (pode levar alguns minutos)';">
            <?= Csrf::field() ?>
            <button type="submit" class="rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-light">
                Regenerar artigo
            </button>
        </form>
    </section>
<?php elseif ($article['status'] === 'BLOCKED'): ?>
    <section class="mt-5 rounded-lg border border-danger/40 bg-danger/10 p-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-danger">Bloqueado</h3>
        <p class="mt-1 text-sm text-text-secondary">
            O limite de <?= $maxAttempts ?> tentativas nesta linhagem foi atingido sem aprovação. Decida o próximo passo — descartar, ou revisar a meta/diretrizes do site antes de tentar um novo tema.
        </p>
    </section>
<?php endif; ?>

<?php if (!empty($feedback)): ?>
    <section class="mt-5 rounded-lg border border-border bg-surface p-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">Feedback de rejeição (<?= count($feedback) ?>)</h3>
        <ul class="mt-2 space-y-3 text-sm">
            <?php foreach ($feedback as $f): ?>
                <li class="border-l-2 border-danger/50 pl-3">
                    <p class="font-medium text-text-primary">
                        <?php if (!empty($f['attempt_number'])): ?><span class="text-xs font-normal text-text-muted">tentativa <?= View::e($f['attempt_number']) ?> · </span><?php endif; ?>
                        <?= View::e(ArticleReviewService::REJECT_REASONS[$f['reason']] ?? $f['reason']) ?>
                        <span class="text-xs font-normal text-text-muted">
                            · <?= View::e($f['created_at']) ?><?php if (!empty($f['author'])): ?> · <?= View::e($f['author']) ?><?php endif; ?>
                        </span>
                    </p>
                    <p class="mt-1 text-text-secondary"><?= nl2br(View::e($f['justification'])) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
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

<?php if (!empty($images)): ?>
    <?php
    $featured = array_values(array_filter($images, static fn ($i) => $i['role'] === 'FEATURED'));
    $body = array_values(array_filter($images, static fn ($i) => $i['role'] === 'BODY'));
    $base = '/sites/' . View::e($site['id']) . '/production/' . View::e($article['id']);
    $deleteForm = static function (array $img) use ($base): void {
        echo '<form method="post" action="' . $base . '/images/' . View::e($img['id']) . '/delete"'
            . ' onsubmit="return confirm(\'Remover esta imagem?\');" class="mt-2 text-right">';
        echo Csrf::field();
        echo '<button type="submit" class="text-xs text-text-muted hover:text-danger">Remover</button>';
        echo '</form>';
    };
    ?>
    <section id="imagens" class="mt-6">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-text-muted">
            Imagens (<?= count($images) ?>)
        </h3>

        <?php if ($featured !== []): ?>
            <p class="mt-3 text-sm font-medium text-text-primary">Imagem destacada — <?= count($featured) ?> opção(ões)</p>
            <p class="text-xs text-text-muted">A escolha é do Redator-Chefe. Marque uma e salve.</p>
            <form method="post" action="<?= $base ?>/images/select" class="mt-2">
                <?= Csrf::field() ?>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <?php foreach ($featured as $i): $sel = (int) $i['selected'] === 1; ?>
                        <label class="block cursor-pointer rounded-lg border <?= $sel ? 'border-success' : 'border-border' ?> bg-surface p-2 focus-within:border-cyan">
                            <img src="<?= View::e($i['url']) ?>" alt="<?= View::e($i['alt_text'] ?? '') ?>" loading="lazy" class="w-full rounded" />
                            <span class="mt-2 flex items-center gap-2 text-xs text-text-secondary">
                                <input type="radio" name="image_id" value="<?= View::e($i['id']) ?>" <?= $sel ? 'checked' : '' ?>
                                       class="accent-success" />
                                <?= $sel ? '<span class="font-semibold text-success">✓ escolhida</span>' : 'usar esta' ?>
                                <span class="text-text-muted">· <?= View::e($i['format'] ?? '') ?></span>
                            </span>
                            <?php if (!empty($i['alt_text'])): ?>
                                <span class="mt-1 block text-xs text-text-muted">alt: <?= View::e($i['alt_text']) ?></span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="mt-3 rounded-md bg-cyan px-4 py-2 text-sm font-semibold text-[#050B0F] hover:bg-cyan-light">
                    Salvar imagem destacada
                </button>
            </form>
        <?php endif; ?>

        <?php if ($body !== []): ?>
            <p class="mt-5 text-sm font-medium text-text-primary">Imagens do corpo — <?= count($body) ?></p>
            <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($body as $i): ?>
                    <figure class="rounded-lg border border-border bg-surface p-2">
                        <img src="<?= View::e($i['url']) ?>" alt="<?= View::e($i['alt_text'] ?? '') ?>" loading="lazy" class="w-full rounded" />
                        <figcaption class="mt-2 text-xs text-text-muted"><?= View::e($i['format'] ?? '') ?></figcaption>
                        <?php if (!empty($i['alt_text'])): ?>
                            <p class="mt-1 text-xs text-text-secondary">alt: <?= View::e($i['alt_text']) ?></p>
                        <?php endif; ?>
                        <?php $deleteForm($i); ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
        <?php if (!empty($review['concerns']) && is_array($review['concerns'])): ?>
            <ul class="mt-2 list-disc pl-5 text-sm text-text-secondary">
                <?php foreach ($review['concerns'] as $c): ?>
                    <?php if (!is_array($c)) { continue; } ?>
                    <li><?php if (!empty($c['area'])): ?><span class="text-text-muted">[<?= View::e($c['area']) ?>]</span> <?php endif; ?><?= View::e($c['note'] ?? '') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if (!empty($review['assumptions_made']) && is_array($review['assumptions_made'])): ?>
            <p class="mt-2 text-xs text-text-muted">Suposições da IA: <?= View::e(implode(' · ', array_map('strval', $review['assumptions_made']))) ?></p>
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
    if (!is_array($i)) { continue; }
    $seoItems[] = '[' . ($i['severity'] ?? '?') . '] ' . ($i['item'] ?? '') . (empty($i['fix']) ? '' : ' → ' . $i['fix']);
}
$compItems = [];
foreach ((array) ($compliance['blocking'] ?? []) as $b) {
    if (!is_array($b)) { continue; }
    $compItems[] = ($b['rule'] ?? '') . (empty($b['fix']) ? '' : ' → ' . $b['fix']);
}
foreach ((array) ($compliance['warnings'] ?? []) as $w) {
    if (!is_array($w)) { continue; }
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
