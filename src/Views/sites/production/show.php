<?php

declare(strict_types=1);

use App\Services\ArticleReviewService;
use App\Support\Csrf;
use App\Support\Icon;
use App\Support\ImageUploadValidator;
use App\Support\Labels;
use App\View;

/** @var array<string,mixed> $site */
/** @var array<string,mixed> $article */
/** @var array<string,mixed>|null $version */
/** @var list<array<string,mixed>> $sources */
/** @var list<array{title:string, link:string}> $internalCandidates */
/** @var list<array<string,mixed>> $executions */
/** @var float $totalCost */
/** @var array<string,array<string,mixed>> $notes */
/** @var list<array<string,mixed>> $images */
/** @var list<array<string,mixed>> $feedback */
/** @var array<string,mixed>|null $schedule */
/** @var array<string,mixed>|null $lastSchedule */
/** @var list<array<string,mixed>> $authors */
/** @var string|null $nextSlot data/hora (Y-m-d H:i:s) que o agendamento automático vai usar, quando o artigo está APPROVED */
/** @var array<string, array{ok: bool, label: string, detail: string}>|null $checklist checklist de pré-aprovação (RF-008), só quando IN_REVIEW */

$activeTab = 'production';
require __DIR__ . '/../_tabs.php';
?>
<style>
    /* Leitura do corpo (fora do modo de edição) igual ao editor visual
     * (achado real 2026-09-14, pedido explícito): fundo branco, texto
     * preto, fonte padrão — sem nada da identidade visual escura/cyan do
     * app, pra ler igual um documento normal, não uma tela de HUD.
     */
    .article-body {
        background: #ffffff;
        color: #1e1e1e;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
        font-size: 16px;
        line-height: 1.6;
    }
    .article-body h2 { font-size: 1.25rem; font-weight: 700; margin: 1.5rem 0 .5rem; color: #1e1e1e; }
    .article-body h3 { font-size: 1.05rem; font-weight: 600; margin: 1.25rem 0 .5rem; color: #1e1e1e; }
    .article-body p { margin: .75rem 0; line-height: 1.7; }
    .article-body ul, .article-body ol { margin: .75rem 0; padding-left: 1.5rem; list-style: revert; }
    .article-body a { color: #0073aa; text-decoration: underline; }
    .article-body a:hover { color: #00a0d2; }
    .article-body table { border-collapse: collapse; margin: 1rem 0; }
    .article-body th, .article-body td { border: 1px solid #d0d0d0; padding: .4rem .6rem; }
</style>

<?php
/* =========================================================================
   Redesign 2026-09-22 (pedido explícito): tudo ACIMA do corpo — cabeçalho,
   alertas, sinais de qualidade (checklist/parecer da IA/SEO/compliance),
   ação do momento (revisar/agendar/publicar/regenerar) e apoio (feedback,
   imagens, detalhes técnicos) — reorganizado pra contar uma história em
   ordem de leitura: "o que é isto" -> "está pronto?" -> "o que eu faço
   agora" -> "detalhes de apoio". O corpo e o editor do corpo (abaixo) não
   mudam. Dois helpers pequenos pra não repetir o mesmo cabeçalho de seção
   (ícone + título + legenda) de formas ligeiramente diferentes em cada
   bloco, como estava antes. */
$sectionHeading = static function (string $icon, string $title, ?string $subtitle = null): void {
    echo '<div class="flex items-center gap-2">'
        . '<span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-cyan/10 text-cyan [&>svg]:h-4 [&>svg]:w-4">' . $icon . '</span>'
        . '<h3 class="font-display text-base font-semibold text-text-primary">' . View::e($title) . '</h3></div>';
    if ($subtitle !== null) {
        echo '<p class="mt-1 text-xs text-text-secondary">' . View::e($subtitle) . '</p>';
    }
};
$alertIcon = static function (string $kind): string {
    $path = $kind === 'danger'
        ? '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>'
        : '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/>';
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};
?>

<a href="/sites/<?= View::e($site['id']) ?>/production" class="inline-flex items-center gap-1 text-sm text-text-secondary hover:text-text-primary">
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
    Produção
</a>

<?php
// Cabeçalho: mesmo sistema de card/tag por status já usado na lista de
// Produção (index.php) — abrir um rascunho a partir da lista agora bate
// visualmente com o cartão que o trouxe até aqui, em vez de virar uma tela
// com paleta diferente.
$statusTone = Labels::articleStatusTone((string) $article['status']);
$isGenerating = in_array($article['status'], ['PLANNED', 'IN_PROGRESS'], true);
?>
<div class="article-card <?= Labels::articleCardTone($statusTone) ?> mt-3 rounded-2xl" style="--card-enter: 0ms">
    <span class="article-card-fx" aria-hidden="true"></span>
    <span class="article-card-tag">
        <?php if ($isGenerating): ?><span class="status-dot h-1.5 w-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span><?php endif; ?>
        <?= View::e(Labels::articleStatus((string) $article['status'])) ?>
    </span>

    <div class="p-5 pt-8 sm:p-6 sm:pt-8">
        <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-cyan/10 text-cyan"><?= Icon::nav('production') ?></span>
            <div class="min-w-0 flex-1">
                <h1 class="font-display text-xl font-bold leading-snug text-text-primary sm:text-2xl">
                    <?= View::e($article['title'] ?: 'Rascunho #' . $article['id']) ?>
                </h1>
                <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <?php if ((int) ($article['attempt_number'] ?? 1) > 1): ?>
                        <span class="article-card-fact"><span class="article-card-icon"><?= Icon::nav('clock') ?></span>tentativa <?= View::e($article['attempt_number']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($article['focus_keyword'])): ?>
                        <span class="article-card-fact"><span class="article-card-icon"><?= Icon::nav('search') ?></span><?= View::e($article['focus_keyword']) ?></span>
                    <?php endif; ?>
                    <span class="article-card-fact"><span class="article-card-icon"><?= Icon::nav('reports') ?></span>US$ <?= number_format($totalCost, 4) ?> gastos</span>
                </div>
            </div>
        </div>

        <?php if ($isGenerating): ?>
            <p role="status" class="mt-4 flex items-center gap-2 text-sm text-text-secondary">
                A IA está gerando este rascunho em segundo plano — a página atualiza sozinha.
            </p>
            <div class="loading-bar-track mt-2 max-w-xs" role="progressbar" aria-label="A IA está gerando este rascunho">
                <div class="loading-bar-fill"></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($article['meta_description'])): ?>
            <p class="mt-4 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-text-secondary">
                <span class="font-semibold text-text-primary">Meta descrição:</span> <?= View::e($article['meta_description']) ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if (trim((string) ($article['writer_request'] ?? '')) !== ''): ?>
    <?php // Pedido específico do redator (botão "Rascunho específico"): quem revisa confere se o texto atendeu. ?>
    <section id="pedido-do-redator" class="mt-4 rounded-xl border border-cyan/40 bg-cyan/5 p-4">
        <h2 class="flex items-center gap-2 font-display text-sm font-semibold text-text-primary">
            <span class="flex h-7 w-7 items-center justify-center rounded-md bg-cyan/10 text-cyan [&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('production') ?></span>
            Pedido do redator
        </h2>
        <p class="mt-2 whitespace-pre-line text-sm text-text-primary"><?= View::e((string) $article['writer_request']) ?></p>
        <p class="mt-3 text-xs text-text-muted">
            A IA seguiu este pedido em todos os passos (e continua seguindo se o artigo for regenerado).
            Antes de aprovar, confira se o texto cobriu cada ponto pedido.
        </p>
    </section>
<?php endif; ?>

<?php $linkRotAt = $notes['pipeline']['link_rot_detected_at'] ?? null; ?>
<?php if ($linkRotAt !== null): ?>
    <div role="alert" class="mt-4 flex gap-3 rounded-xl border border-danger/40 bg-danger/10 p-4">
        <span class="shrink-0 text-danger"><?= $alertIcon('danger') ?></span>
        <div class="text-sm text-text-primary">
            <p class="font-semibold text-danger">Link rot detectado</p>
            <p class="mt-1">
                <?= View::e(count((array) ($notes['pipeline']['link_rot_dead_urls'] ?? []))) ?> link(s) de fonte que estavam
                vivos na publicação não respondem mais (checado em <?= View::e(date('d/m/Y H:i', strtotime((string) $linkRotAt))) ?>).
                Resolva na <a href="/sites/<?= View::e($site['id']) ?>/links" class="font-semibold text-danger underline hover:text-[#ff8fa3]">Central de Links</a>.
            </p>
        </div>
    </div>
<?php endif; ?>

<?php $pipelineWarnings = (array) ($notes['pipeline']['warnings'] ?? []); ?>
<?php if ($pipelineWarnings !== []): ?>
    <div role="alert" class="mt-4 flex gap-3 rounded-xl border border-warning/40 bg-warning/10 p-4">
        <span class="shrink-0 text-warning"><?= $alertIcon('warning') ?></span>
        <div class="min-w-0 flex-1 text-sm text-text-primary">
            <p class="font-semibold text-warning">Avisos automáticos da geração</p>
            <ul class="mt-2 list-disc space-y-1.5 pl-5">
                <?php foreach ($pipelineWarnings as $w): ?>
                    <li><?= View::e((string) $w) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php if ($article['status'] === 'ERROR'): ?>
    <?php
    $lastFailure = null;
    foreach (array_reverse($executions) as $execution) {
        if ($execution['status'] === 'FAILED') {
            $lastFailure = $execution;
            break;
        }
    }
    ?>
    <div role="alert" class="mt-4 flex gap-3 rounded-xl border border-danger/40 bg-danger/10 p-4">
        <span class="shrink-0 text-danger"><?= $alertIcon('danger') ?></span>
        <p class="text-sm text-text-primary">
            <span class="font-semibold text-danger">Falha técnica na geração<?= $lastFailure !== null ? ' (passo: ' . View::e($lastFailure['step']) . ')' : '' ?>.</span>
            <?= $lastFailure !== null && $lastFailure['error_message'] ? View::e($lastFailure['error_message']) : 'Sem detalhe registrado.' ?>
        </p>
    </div>
<?php endif; ?>

<?php
// --- Qualidade do artigo -------------------------------------------------
// Os 4 sinais automáticos (checklist obrigatório, parecer livre da IA, SEO,
// compliance) viviam espalhados em 3 pontos diferentes da página (um logo
// no topo, dois lá embaixo depois das imagens) — juntos aqui, dá pra
// responder "está pronto?" olhando um só lugar, antes de decidir qualquer
// coisa. A decisão de aprovar continua exigindo só o checklist (RF-008);
// os outros 3 são consultivos.
$review = $notes['review'] ?? null;
$seo = $notes['seo'] ?? null;
$compliance = $notes['compliance'] ?? null;
$checklistPassed = $checklist !== null && ArticleReviewService::checklistPassed($checklist);
$hasQualitySignals = $checklist !== null || $review !== null || $seo !== null || $compliance !== null;

$recommendationBadge = static function (string $rec): string {
    [$cls, $label] = match ($rec) {
        'ready_for_human' => ['border-success/40 bg-success/15 text-success', 'pronto pra revisão'],
        'needs_fix'        => ['border-warning/40 bg-warning/15 text-warning', 'precisa de ajuste'],
        default            => ['border-border bg-surface-2 text-text-secondary', $rec],
    };
    return '<span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold ' . $cls . '">' . View::e($label) . '</span>';
};
$qualityCardOpen = static function (string $icon, string $title, string $badgeHtml): void {
    echo '<div class="rounded-xl border border-border bg-surface p-4">'
        . '<div class="flex items-center justify-between gap-2">'
        . '<span class="flex items-center gap-2 text-sm font-semibold text-text-primary">'
        . '<span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface-2 text-text-secondary [&>svg]:h-3.5 [&>svg]:w-3.5">' . $icon . '</span>'
        . View::e($title) . '</span>' . $badgeHtml . '</div>';
};
/** @param list<array{text:string, severity:string, evidence?:string}> $items */
$gateBody = static function (array $items): void {
    if ($items === []) {
        return;
    }
    echo '<ul class="mt-3 space-y-2 text-sm text-text-secondary">';
    foreach ($items as $it) {
        $sevCls = match ($it['severity']) {
            'block' => 'border-danger/40 bg-danger/15 text-danger',
            'warn', 'aviso' => 'border-warning/40 bg-warning/15 text-warning',
            default => 'border-border bg-surface-2 text-text-secondary',
        };
        $sevLabel = match ($it['severity']) {
            'block' => 'bloqueio', 'warn', 'aviso' => 'aviso', default => $it['severity'],
        };
        echo '<li class="flex items-start gap-2"><span class="mt-0.5 shrink-0 rounded-full border px-1.5 py-0.5 text-[10px] font-semibold uppercase ' . $sevCls . '">' . View::e($sevLabel) . '</span>'
            . '<span class="text-text-primary">' . View::e($it['text']);
        if (!empty($it['evidence'])) {
            echo '<span class="mt-1 block rounded border border-border bg-surface-2 px-2 py-1 text-xs italic text-text-muted">"' . View::e($it['evidence']) . '"</span>';
        }
        echo '</span></li>';
    }
    echo '</ul>';
};
$seoItems = [];
foreach ((array) ($seo['issues'] ?? []) as $i) {
    if (!is_array($i)) { continue; }
    $seoItems[] = ['severity' => (string) ($i['severity'] ?? '?'), 'text' => ($i['item'] ?? '') . (empty($i['fix']) ? '' : ' → ' . $i['fix'])];
}
$compItems = [];
foreach ((array) ($compliance['blocking'] ?? []) as $b) {
    if (!is_array($b)) { continue; }
    $compItems[] = [
        'severity' => 'block',
        'text'     => ($b['rule'] ?? '') . (empty($b['fix']) ? '' : ' → ' . $b['fix']),
        'evidence' => (string) ($b['evidence'] ?? ''),
    ];
}
foreach ((array) ($compliance['warnings'] ?? []) as $w) {
    if (!is_array($w)) { continue; }
    $compItems[] = ['severity' => 'aviso', 'text' => ($w['rule'] ?? '') . (empty($w['note']) ? '' : ': ' . $w['note'])];
}
$compBlockingCount = count((array) ($compliance['blocking'] ?? []));
?>
<?php if ($hasQualitySignals): ?>
    <section class="mt-6">
        <?php $sectionHeading(Icon::nav('check'), 'Qualidade do artigo', 'Sinais automáticos pra ajudar a decidir — a decisão final é sempre sua.'); ?>
        <div class="mt-3 grid gap-3 lg:grid-cols-2">
            <?php if ($checklist !== null): ?>
                <?php
                $okCount = 0;
                foreach ($checklist as $item) { if ($item['ok']) { $okCount++; } }
                $badge = '<span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold '
                    . ($checklistPassed ? 'border-success/40 bg-success/15 text-success' : 'border-danger/40 bg-danger/15 text-danger')
                    . '">' . $okCount . '/' . count($checklist) . ' ok</span>';
                $qualityCardOpen(Icon::nav('check'), 'Checklist de pré-aprovação', $badge);
                ?>
                <p class="mt-1 text-xs text-text-muted">Obrigatório — aprovar só é permitido com os 3 itens ok.</p>
                <ul class="mt-3 space-y-2">
                    <?php foreach ($checklist as $item): ?>
                        <li class="flex items-start gap-2 text-sm">
                            <span class="mt-0.5 shrink-0 rounded-full border px-1.5 py-0.5 text-[10px] font-semibold uppercase <?= $item['ok'] ? 'border-success/40 bg-success/15 text-success' : 'border-danger/40 bg-danger/15 text-danger' ?>">
                                <?= $item['ok'] ? 'ok' : 'falhou' ?>
                            </span>
                            <span>
                                <span class="block font-medium text-text-primary"><?= View::e($item['label']) ?></span>
                                <span class="text-text-secondary"><?= View::e($item['detail']) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                </div>
            <?php endif; ?>

            <?php if ($review !== null): ?>
                <?php $qualityCardOpen(Icon::nav('ai'), 'Parecer da IA', $recommendationBadge((string) ($review['recommendation'] ?? ''))); ?>
                <p class="mt-2 text-sm text-text-primary"><?= View::e($review['summary'] ?? '') ?></p>
                <?php if (!empty($review['concerns']) && is_array($review['concerns'])): ?>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-text-secondary">
                        <?php foreach ($review['concerns'] as $c): ?>
                            <?php if (!is_array($c)) { continue; } ?>
                            <li><?php if (!empty($c['area'])): ?><span class="font-medium text-text-primary"><?= View::e($c['area']) ?>:</span> <?php endif; ?><?= View::e($c['note'] ?? '') ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (!empty($review['assumptions_made']) && is_array($review['assumptions_made'])): ?>
                    <p class="mt-2 text-xs text-text-muted">Suposições da IA: <?= View::e(implode(' · ', array_map('strval', $review['assumptions_made']))) ?></p>
                <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($seo !== null): ?>
                <?php
                $seoOk = (bool) ($seo['passes'] ?? false);
                $badge = '<span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold ' . ($seoOk ? 'border-success/40 bg-success/15 text-success' : 'border-danger/40 bg-danger/15 text-danger') . '">' . ($seoOk ? 'ok' : 'com pendências') . '</span>';
                $qualityCardOpen(Icon::nav('search'), 'SEO', $badge);
                $gateBody($seoItems);
                ?>
                </div>
            <?php endif; ?>

            <?php if ($compliance !== null): ?>
                <?php
                $compOk = (bool) ($compliance['approved'] ?? false);
                $badge = '<span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-semibold ' . ($compOk ? 'border-success/40 bg-success/15 text-success' : 'border-danger/40 bg-danger/15 text-danger') . '">' . ($compOk ? 'ok' : 'com pendências') . '</span>';
                $qualityCardOpen(Icon::nav('shield'), 'Compliance', $badge);
                ?>
                <p class="mt-1 text-xs text-text-muted">
                    <span class="font-semibold text-danger">Bloqueio</span> = fere uma regra de compliance/AdSense (docs/editorial/compliance.md) — leia o trecho citado e confira antes de aprovar.
                    <span class="font-semibold text-warning">Aviso</span> = vale revisar, mas não impede.
                </p>
                <?php $gateBody($compItems); ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($article['status'] === 'IN_REVIEW'): ?>
    <section data-tour="review-actions" class="mt-6">
        <?php $sectionHeading(Icon::nav('production'), 'Decisão do Redator-Chefe'); ?>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-success/40 bg-success/5 p-4">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-success/15 text-success" aria-hidden="true">
                        <?= Icon::nav('check') ?>
                    </span>
                    <p class="font-display text-sm font-semibold text-text-primary">Aprovar</p>
                </div>
                <p class="mt-2 text-sm text-text-secondary">Libera o agendamento de publicação.</p>
                <?php if (!$checklistPassed): ?>
                    <p class="mt-2 text-xs text-warning">Checklist acima não passou — ajuste o artigo antes de aprovar.</p>
                <?php endif; ?>
                <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($article['id']) ?>/approve" class="mt-3"
                      data-confirm="Aprovar este artigo? Ele libera pra agendamento de publicação.">
                    <?= Csrf::field() ?>
                    <?php if ($compBlockingCount > 0): ?>
                        <label class="mb-3 flex items-start gap-2 text-xs text-text-secondary">
                            <input type="checkbox" name="compliance_ack" value="1" required class="mt-0.5 shrink-0">
                            <span>Revisei a(s) <?= $compBlockingCount ?> pendência(s) de compliance acima e decido aprovar assim mesmo.</span>
                        </label>
                    <?php endif; ?>
                    <button type="submit" <?= $checklistPassed ? '' : 'disabled title="Checklist de pré-aprovação não passou"' ?>
                            class="btn btn-success px-4 py-2 text-sm">
                        Aprovar artigo
                    </button>
                </form>
            </div>

            <div class="rounded-xl border border-danger/40 bg-danger/5 p-4">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-danger/15 text-danger" aria-hidden="true">
                        <?= Icon::nav('close') ?>
                    </span>
                    <p class="font-display text-sm font-semibold text-text-primary">Rejeitar</p>
                </div>
                <p class="mt-2 text-sm text-text-secondary">Registra o motivo e permite regenerar.</p>
                <details class="mt-3">
                    <summary class="btn btn-danger px-4 py-2 text-sm">
                        Rejeitar artigo
                    </summary>
                    <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($article['id']) ?>/reject"
                          class="mt-3 flex flex-col gap-2"
                          onsubmit="return this.justification.value.trim() !== '' || (alert('Escreva a justificativa.'), false);">
                        <?= Csrf::field() ?>
                        <label class="text-sm">
                            <span class="block font-medium text-text-secondary">Motivo da rejeição</span>
                            <select name="reason" required data-compliance-prefill
                                    data-compliance-summary="<?= View::e(implode(' | ', array_map(
                                        static fn (array $it): string => $it['text'],
                                        array_filter($compItems, static fn (array $it): bool => $it['severity'] === 'block'),
                                    ))) ?>"
                                    class="mt-1 w-full">
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
                        <button type="submit" class="btn btn-danger self-start px-4 py-2 text-sm">
                            Confirmar rejeição
                        </button>
                    </form>
                </details>
            </div>
        </div>
        <script>
        (function () {
            // Escolher "Problema de compliance" pré-preenche a justificativa com as
            // pendências que a IA já apontou — evita o redator ter que reabrir o card
            // de Compliance acima e retranscrever à mão. Só preenche se o campo ainda
            // estiver vazio, pra nunca sobrescrever algo que a pessoa já escreveu.
            var select = document.querySelector('select[data-compliance-prefill]');
            if (!select) { return; }
            select.addEventListener('change', function () {
                var summary = select.dataset.complianceSummary || '';
                var textarea = select.closest('form').querySelector('textarea[name="justification"]');
                if (select.value === 'problema_compliance' && summary !== '' && textarea && textarea.value.trim() === '') {
                    textarea.value = summary;
                }
            });
        })();
        </script>
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
    <section id="agendar" class="mt-6 rounded-xl border border-border bg-surface p-5">
        <?php $sectionHeading(Icon::nav('calendar'), 'Agendar publicação'); ?>
        <?php if ($authors === []): ?>
            <p class="mt-3 text-sm text-text-secondary">
                Nenhum autor disponível. Sincronize os autores em
                <a href="/sites/<?= View::e($site['id']) ?>/wordpress" class="text-cyan hover:text-cyan-bright">WordPress</a>.
            </p>
        <?php elseif ($featuredSelected === null): ?>
            <p class="mt-3 text-sm text-text-secondary">
                Escolha a <a href="#imagens" class="text-cyan hover:text-cyan-bright">imagem destacada</a> antes de agendar.
            </p>
        <?php else: ?>
            <p class="mt-3 text-sm text-text-secondary">
                Escolha o autor — a imagem destacada já escolhida é usada automaticamente, e a data/hora também:
                <?php if ($nextSlot !== null): ?>
                    <strong class="text-text-primary">
                        vai ser publicado em <?= View::e(date('d/m/Y \à\s H:i', strtotime($nextSlot))) ?>
                    </strong>
                    — o próximo horário livre deste site (sempre 9h). Quiser outra data, dá pra reagendar depois.
                <?php endif; ?>
            </p>
            <?php $ambiguousLinks = (array) ($notes['pipeline']['ambiguous_links'] ?? []); ?>
            <form method="post" action="<?= $scheduleBase ?>/schedule" class="mt-3 grid gap-4 sm:max-w-md">
                <?= Csrf::field() ?>
                <input type="hidden" name="image_id" value="<?= View::e($featuredSelected['id']) ?>">
                <?php if ($ambiguousLinks !== []): ?>
                    <div class="rounded-md border border-warning/40 bg-warning/10 px-3 py-2">
                        <p class="text-sm font-semibold text-warning">
                            <?= count($ambiguousLinks) ?> link(s) de fonte não confirmado(s) automaticamente
                        </p>
                        <p class="mt-1 text-xs text-text-secondary">
                            Bloqueio comum de bot em domínio grande — não significa que estão mortos, mas ninguém
                            confirmou. Abra cada um e marque que conferiu antes de agendar.
                        </p>
                        <ul class="mt-2 space-y-1">
                            <?php foreach ($ambiguousLinks as $url): ?>
                                <li class="text-xs">
                                    <label class="flex items-start gap-2">
                                        <input type="checkbox" name="ambiguous_confirmed[]" value="<?= View::e($url) ?>" required
                                               class="mt-0.5 accent-warning">
                                        <span>
                                            já conferi manualmente —
                                            <a href="<?= View::e($url) ?>" target="_blank" rel="noopener"
                                               class="text-cyan hover:text-cyan-bright break-all"><?= View::e($url) ?></a>
                                        </span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <label class="text-sm">
                    <span class="block font-medium text-text-secondary">Autor</span>
                    <select name="author_id" required
                            class="mt-1 w-full">
                        <?= $authorOptions($authors, 0) ?>
                    </select>
                </label>
                <button type="submit" class="btn btn-primary justify-self-start px-4 py-2 text-sm">
                    Agendar
                </button>
            </form>
        <?php endif; ?>
    </section>
<?php elseif ($article['status'] === 'SCHEDULED' && $schedule !== null): ?>
    <?php $imageIdForForm = $featuredSelected['id'] ?? $schedule['image_id']; ?>
    <section id="agendar" class="mt-6 rounded-xl border border-cyan/40 bg-surface p-5">
        <?php $sectionHeading(Icon::nav('clock'), 'Agendado'); ?>
        <p class="mt-3 text-sm text-text-primary">
            <strong><?= View::e(date('d/m/Y H:i', strtotime((string) $schedule['scheduled_date']))) ?></strong>
            · autor: <?= View::e($schedule['author_name'] ?? '—') ?>
        </p>
        <?php if (!empty($schedule['image_url'])): ?>
            <img src="<?= View::e($schedule['image_url']) ?>" alt="" class="mt-2 w-40 rounded border border-border">
        <?php endif; ?>

        <?php $whenTs = strtotime((string) $schedule['scheduled_date']); ?>
        <form method="post" action="<?= $scheduleBase ?>/schedule/publish" class="mt-4"
              onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Enviando…';">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
                <?= $whenTs > time() ? 'Enviar ao WordPress (agenda para a data)' : 'Publicar no WordPress agora' ?>
            </button>
            <p class="mt-1 text-xs text-text-muted">
                Cria o post <?= $whenTs > time() ? 'como agendado (o WordPress publica sozinho na data)' : 'já publicado' ?>,
                sobe a imagem destacada e define a categoria. Imagens do corpo não entram por ora.
            </p>
        </form>

        <div class="mt-4 flex flex-wrap items-start gap-6">
            <details class="text-sm">
                <summary class="cursor-pointer font-medium text-cyan hover:text-cyan-bright">Reagendar</summary>
                <form method="post" action="<?= $scheduleBase ?>/schedule/update" class="mt-3 grid gap-4 sm:max-w-md">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="image_id" value="<?= View::e($imageIdForForm) ?>">
                    <label class="text-sm">
                        <span class="block font-medium text-text-secondary">Autor</span>
                        <select name="author_id" required
                                class="mt-1 w-full">
                            <?= $authorOptions($authors, (int) $schedule['author_id']) ?>
                        </select>
                    </label>
                    <label class="text-sm">
                        <span class="block font-medium text-text-secondary">Data e hora</span>
                        <input type="datetime-local" name="scheduled_date" required min="<?= $nowLocal ?>"
                               value="<?= View::e(date('Y-m-d\TH:i', strtotime((string) $schedule['scheduled_date']))) ?>"
                               class="mt-1 w-full rounded-md border border-border bg-surface-2 px-3 py-2 text-text-primary focus:border-cyan focus:outline-none">
                    </label>
                    <button type="submit" class="btn btn-primary justify-self-start px-4 py-2 text-sm">
                        Salvar
                    </button>
                </form>
            </details>
            <form method="post" action="<?= $scheduleBase ?>/schedule/cancel"
                  data-confirm="Cancelar o agendamento? O artigo volta para aprovado.">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-danger px-4 py-2 text-sm">
                    Cancelar agendamento
                </button>
            </form>
        </div>
    </section>
<?php elseif ($article['status'] === 'SCHEDULED' && $schedule === null): ?>
    <section id="agendar" class="mt-6 rounded-xl border border-danger/40 bg-danger/10 p-5">
        <?php $sectionHeading(Icon::nav('alert'), 'Falha no envio ao WordPress'); ?>
        <p role="alert" class="mt-3 text-sm text-text-secondary">
            <?php if ($lastSchedule !== null): ?>
                Tentativa automática de publicação (agendada pra
                <?= View::e(date('d/m/Y H:i', strtotime((string) $lastSchedule['scheduled_date']))) ?>) esgotou as
                tentativas.
            <?php else: ?>
                O envio ao WordPress falhou.
            <?php endif; ?>
            Cancele e agende de novo pra tentar mais uma vez.
        </p>
        <form method="post" action="<?= $scheduleBase ?>/schedule/cancel" class="mt-3"
              data-confirm="Cancelar o agendamento? O artigo volta para aprovado.">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-danger px-4 py-2 text-sm">
                Cancelar agendamento
            </button>
        </form>
    </section>
<?php elseif ($article['status'] === 'PUBLISHED' && $lastSchedule !== null && !empty($lastSchedule['wordpress_post_id'])): ?>
    <?php $postUrl = rtrim((string) ($site['wordpress_url'] ?? ''), '/') . '/?p=' . (int) $lastSchedule['wordpress_post_id']; ?>
    <section id="agendar" class="mt-6 rounded-xl border border-success/40 bg-surface p-5">
        <?php $sectionHeading(Icon::nav('wordpress'), 'Publicado'); ?>
        <p class="mt-3 text-sm text-text-primary">
            Post #<?= View::e($lastSchedule['wordpress_post_id']) ?> no WordPress
            <?php if (!empty($lastSchedule['author_name'])): ?> · autor: <?= View::e($lastSchedule['author_name']) ?><?php endif; ?>
            · data: <?= View::e(date('d/m/Y H:i', strtotime((string) $lastSchedule['scheduled_date']))) ?>
        </p>
        <?php if (!empty($site['wordpress_url'])): ?>
            <a href="<?= View::e($postUrl) ?>" target="_blank" rel="noopener"
               class="mt-1 inline-block text-sm text-cyan hover:text-cyan-bright">Abrir no WordPress →</a>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <form method="post" action="<?= $scheduleBase ?>/schedule/republish"
                  data-confirm="Reenviar título, corpo e imagens deste artigo para o post no WordPress? Isso sobrescreve edições feitas direto lá.">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
                    Atualizar no WordPress
                </button>
            </form>
            <form method="post" action="<?= $scheduleBase ?>/schedule/retract"
                  data-confirm="Retirar o post do WordPress (vai para a lixeira lá) e voltar o artigo para aprovado?">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-danger px-4 py-2 text-sm">
                    Retirar do WordPress
                </button>
            </form>
        </div>
        <p class="mt-2 text-xs text-text-muted">
            Para ajustar o texto: regenere o artigo aqui (ele volta pelo fluxo de revisão) ou edite direto no WordPress.
            "Atualizar" reenvia a versão daqui.
        </p>
    </section>
<?php endif; ?>

<?php
$attempt = (int) ($article['attempt_number'] ?? 1);
$maxAttempts = 3;
?>
<?php if ($article['status'] === 'REVISION_REQUESTED'): ?>
    <section class="mt-6 rounded-xl border border-border bg-surface p-5">
        <?php $sectionHeading(Icon::nav('production'), 'Regeneração'); ?>
        <p class="mt-3 text-sm text-text-secondary">
            Tentativa <?= $attempt ?> de <?= $maxAttempts ?>.
            <?php if ($attempt >= $maxAttempts): ?>
                Esta é a última — se a próxima for rejeitada, o artigo fica <strong>bloqueado</strong> para decisão sua.
            <?php else: ?>
                A IA recebe o motivo da rejeição e refaz o artigo (pesquisa, texto, SEO, imagens). Leva alguns minutos e tem custo.
            <?php endif; ?>
        </p>
        <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($article['id']) ?>/regenerate"
              class="mt-3"
              onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Iniciando…';">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
                Regenerar artigo
            </button>
        </form>
    </section>
<?php elseif ($article['status'] === 'ERROR'): ?>
    <section class="mt-6 rounded-xl border border-border bg-surface p-5">
        <?php $sectionHeading(Icon::nav('production'), 'Tentar de novo'); ?>
        <p class="mt-3 text-sm text-text-secondary">
            Tentativa <?= $attempt ?> de <?= $maxAttempts ?> — a falha foi técnica (ver acima), não uma rejeição do
            Redator-Chefe.
            <?php if ($attempt >= $maxAttempts): ?>
                Esta é a última — se falhar de novo, o artigo fica <strong>bloqueado</strong> para decisão sua.
            <?php else: ?>
                Cria uma nova tentativa nesta linhagem; a IA refaz o artigo do zero. Leva alguns minutos e tem custo.
            <?php endif; ?>
        </p>
        <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($article['id']) ?>/regenerate"
              class="mt-3"
              onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Iniciando…';">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
                Tentar de novo
            </button>
        </form>
    </section>
<?php elseif ($article['status'] === 'BLOCKED'): ?>
    <section class="mt-6 rounded-xl border border-danger/40 bg-danger/10 p-5">
        <?php $sectionHeading(Icon::nav('alert'), 'Bloqueado'); ?>
        <p class="mt-3 text-sm text-text-secondary">
            O limite de <?= $maxAttempts ?> tentativas nesta linhagem foi atingido (rejeição e/ou falha técnica), sem aprovação. Decida o próximo passo — descartar, ou revisar a meta/diretrizes do site antes de tentar um novo tema.
        </p>
    </section>
<?php endif; ?>

<?php if (!empty($feedback)): ?>
    <section class="mt-6 rounded-xl border border-border bg-surface p-5">
        <?php $sectionHeading(Icon::nav('feedback'), 'Feedback de rejeição (' . count($feedback) . ')'); ?>
        <ul class="mt-3 space-y-3 text-sm">
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

<?php if (empty($images) && $isGenerating): ?>
    <section id="imagens" class="mt-6 rounded-xl border border-border bg-surface p-5" role="status" aria-live="polite">
        <?php $sectionHeading(Icon::nav('camera'), 'Imagens'); ?>
        <div class="mt-4 flex items-center gap-4 rounded-lg border border-dashed border-cyan/40 bg-cyan/5 p-5">
            <span class="spinner shrink-0 text-4xl text-cyan" aria-hidden="true"></span>
            <div>
                <p class="text-sm font-semibold text-text-primary">As imagens estão sendo geradas…</p>
                <p class="text-xs text-text-muted">Chegam ao fim da produção do artigo. Esta página se atualiza sozinha — e depois você poderá enviar uma imagem sua aqui.</p>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php // Aparece mesmo sem nenhuma imagem (ex.: a geração falhou) — quem revisa pode subir a sua. ?>
<?php if (!empty($images) || !in_array($article['status'], ['PLANNED', 'IN_PROGRESS'], true)): ?>
    <?php
    $featured = array_values(array_filter($images, static fn ($i) => $i['role'] === 'FEATURED'));
    $body = array_values(array_filter($images, static fn ($i) => $i['role'] === 'BODY'));
    $base = '/sites/' . View::e($site['id']) . '/production/' . View::e($article['id']);
    $deleteForm = static function (array $img) use ($base): void {
        echo '<form method="post" action="' . $base . '/images/' . View::e($img['id']) . '/delete"'
            . ' data-confirm="Remover esta imagem?" class="flex-1">';
        echo Csrf::field();
        echo '<button type="submit" class="btn btn-danger flex w-full items-center justify-center gap-1.5 px-3 py-1.5 text-xs">'
            . '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/>'
            . '</svg>Remover</button>';
        echo '</form>';
    };
    // Substituir (Fase de imagens, pedido 2026-09-22): reenvia o mesmo prompt
    // dessa imagem pro gerador — troca uma pela outra, sem contar como uma
    // imagem a mais. Some do carregamento normal do form (onsubmit) porque a
    // chamada de IA + imagem demora alguns segundos, mesmo padrão já usado
    // em "Sugerir por relevância"/"Gerar mais" do editor de corpo.
    $regenerateForm = static function (array $img) use ($base): void {
        echo '<form method="post" action="' . $base . '/images/' . View::e($img['id']) . '/regenerate" class="flex-1"'
            . ' onsubmit="var b=this.querySelector(\'button\');b.disabled=true;b.innerHTML=\'<span class=&quot;spinner&quot;></span> Gerando…\';">';
        echo Csrf::field();
        echo '<button type="submit" class="btn btn-secondary flex w-full items-center justify-center gap-1.5 px-3 py-1.5 text-xs">'
            . '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M20 11A8 8 0 1 0 18.3 16"/><path d="M20 5v6h-6"/>'
            . '</svg>Substituir</button>';
        echo '</form>';
    };
    ?>
    <section id="imagens" class="mt-6 rounded-xl border border-border bg-surface p-5">
        <?php $sectionHeading(Icon::nav('camera'), 'Imagens (' . count($images) . ')'); ?>
        <?php if ($isGenerating): ?>
            <p class="mt-3 flex items-center gap-2 text-xs text-cyan" role="status">
                <span class="spinner" aria-hidden="true"></span> Ainda gerando imagens — esta página se atualiza sozinha.
            </p>
        <?php endif; ?>

        <?php if ($featured !== []): ?>
            <p class="mt-4 text-sm font-medium text-text-primary">Imagem destacada — <?= count($featured) ?> opção(ões)</p>
            <p class="text-xs text-text-muted">A escolha é do Redator-Chefe. Use as setas pra navegar, veja ampliada e selecione a que ficar.</p>
            <form method="post" action="<?= $base ?>/images/select" class="mt-2">
                <?= Csrf::field() ?>
                <div class="image-carousel mx-auto w-full" data-carousel>
                    <div class="relative rounded-lg border border-border bg-surface p-2">
                        <div class="overflow-hidden rounded" data-carousel-viewport>
                            <div class="flex" data-carousel-track>
                                <?php foreach ($featured as $i): $sel = (int) $i['selected'] === 1; ?>
                                    <div class="w-full shrink-0 px-1" data-carousel-slide>
                                        <span class="relative block overflow-hidden rounded">
                                            <img src="<?= View::e($i['url']) ?>" alt="<?= View::e($i['alt_text'] ?? '') ?>" loading="lazy" class="w-full rounded" />
                                            <button type="button"
                                                    data-lightbox="<?= View::e($i['url']) ?>"
                                                    aria-label="Ver imagem em tamanho grande"
                                                    class="absolute inset-0 flex items-center justify-center bg-[#050B0F]/40 opacity-0 transition hover:opacity-100 focus-visible:opacity-100">
                                                <span class="rounded-md bg-surface/90 px-3 py-1.5 text-sm font-medium text-text-primary">Ver ampliado</span>
                                            </button>
                                            <?php if ($sel): ?>
                                                <span class="image-pick-badge" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6"/></svg>
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Setas centralizadas só na altura da imagem (não do card inteiro com
                             legenda embaixo, que varia de tamanho conforme o alt text) — por isso
                             ficam de fora do trilho que desliza, mas dentro deste wrapper que só
                             contém a imagem. -->
                        <button type="button" data-carousel-prev aria-label="Imagem anterior"
                                class="absolute left-3 top-1/2 z-10 -translate-y-1/2 flex h-11 w-11 items-center justify-center rounded-full border border-border bg-surface/90 text-text-primary shadow-lg hover:border-cyan hover:text-cyan">
                            <svg class="pointer-events-none" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
                        </button>
                        <button type="button" data-carousel-next aria-label="Próxima imagem"
                                class="absolute right-3 top-1/2 z-10 -translate-y-1/2 flex h-11 w-11 items-center justify-center rounded-full border border-border bg-surface/90 text-text-primary shadow-lg hover:border-cyan hover:text-cyan">
                            <svg class="pointer-events-none" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                        </button>

                        <?php if (count($featured) > 1): ?>
                            <p class="pointer-events-none absolute bottom-2 left-1/2 z-10 -translate-x-1/2 rounded-full bg-[#050B0F]/70 px-2.5 py-1 text-xs font-medium text-text-primary">
                                <span data-carousel-counter>1 / <?= count($featured) ?></span>
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php // Seleção (botão grande) e alt text ficam fora do trilho, sincronizados
                    // com a imagem atual via JS (hidden por índice) — não sofrem a transição de
                    // slide (não precisam, é só texto/estado) e não afetam a altura usada pra
                    // centralizar as setas acima. ?>
                    <?php foreach ($featured as $idx => $i): $sel = (int) $i['selected'] === 1; ?>
                        <div data-carousel-caption <?= $idx === 0 ? '' : 'hidden' ?> class="mt-3">
                            <label class="block cursor-pointer" data-select-image-label>
                                <input type="radio" name="image_id" value="<?= View::e($i['id']) ?>" <?= $sel ? 'checked' : '' ?>
                                       class="sr-only" data-select-image-input />
                                <span data-select-image-pill
                                      class="image-select-pill <?= $sel ? 'image-select-pill-selected' : '' ?> flex w-full items-center justify-center gap-2 rounded-md border-2 px-4 py-3 text-base font-semibold transition">
                                    <svg data-select-image-icon <?= $sel ? '' : 'hidden' ?> viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12.5 9.5 18 20 6"/></svg>
                                    <span data-select-image-text><?= $sel ? 'Imagem selecionada' : 'Usar esta imagem' ?></span>
                                    <span class="font-normal opacity-75">· <?= View::e($i['format'] ?? '') ?><?= trim((string) ($i['prompt'] ?? '')) === '' ? ' · enviada por você' : '' ?></span>
                                </span>
                            </label>
                            <?php if (!empty($i['alt_text'])): ?>
                                <p class="mt-2 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-text-primary">
                                    <span class="font-semibold text-text-secondary">Descrição da imagem (alt):</span>
                                    <?= View::e($i['alt_text']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary mt-3 px-4 py-2 text-sm">
                    Salvar imagem destacada
                </button>
            </form>
        <?php endif; ?>

        <?php if ($body !== []): ?>
            <div class="mt-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-text-primary">Imagens do corpo — <?= count($body) ?></p>
                    <p class="text-xs text-text-muted">
                        Distribuídas automaticamente pelo texto na publicação, sempre na mesma proporção da destacada.
                        A descrição (alt) de cada uma só aparece ao ampliar.
                    </p>
                </div>
                <form method="post" action="<?= $base ?>/images/body/add"
                      onsubmit="var b=this.querySelector('button');b.disabled=true;b.innerHTML='<span class=&quot;spinner&quot;></span> Gerando…';">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn-primary shrink-0 whitespace-nowrap px-3 py-1.5 text-xs">
                        <span class="[&>svg]:h-3.5 [&>svg]:w-3.5"><?= Icon::nav('plus') ?></span>
                        Gerar mais uma
                    </button>
                </form>
            </div>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($body as $i): ?>
                    <figure class="hover-card overflow-hidden rounded-lg border border-border bg-surface p-2">
                        <span class="relative block overflow-hidden rounded">
                            <img src="<?= View::e($i['url']) ?>" alt="<?= View::e($i['alt_text'] ?? '') ?>" loading="lazy" class="w-full rounded" />
                            <button type="button"
                                    data-lightbox="<?= View::e($i['url']) ?>"
                                    data-lightbox-caption="<?= View::e($i['alt_text'] ?? '') ?>"
                                    aria-label="Ver imagem em tamanho grande"
                                    class="absolute inset-0 flex items-center justify-center bg-[#050B0F]/40 opacity-0 transition hover:opacity-100 focus-visible:opacity-100">
                                <span class="rounded-md bg-surface/90 px-3 py-1.5 text-sm font-medium text-text-primary">Ver ampliado</span>
                            </button>
                        </span>
                        <figcaption class="mt-2 text-xs font-medium text-text-secondary">
                            Imagem de corpo <span class="font-normal text-text-muted">· <?= View::e($i['format'] ?? '') ?><?= trim((string) ($i['prompt'] ?? '')) === '' ? ' · enviada por você' : '' ?></span>
                        </figcaption>
                        <div class="mt-3 flex gap-2">
                            <?php // Imagem própria não tem prompt guardado — não há o que regenerar. ?>
                            <?php if (trim((string) ($i['prompt'] ?? '')) !== ''): $regenerateForm($i); endif; ?>
                            <?php $deleteForm($i); ?>
                        </div>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php
        $upMinW = ImageUploadValidator::MIN_WIDTH;
        $upMaxW = ImageUploadValidator::MAX_WIDTH;
        $upMinH = ImageUploadValidator::minHeight();
        $upRatio = ImageUploadValidator::RATIO_W . ':' . ImageUploadValidator::RATIO_H;
        $upMb = ImageUploadValidator::MAX_BYTES / 1048576;
        ?>
        <div class="mt-6 rounded-lg border border-border bg-surface-2 p-4">
            <p class="text-sm font-semibold text-text-primary">Enviar uma imagem sua</p>
            <p class="mt-1 text-xs text-text-muted">Use quando preferir uma foto ou arte própria no lugar das geradas pela IA.</p>

            <div class="mt-3 rounded-md border border-cyan/40 bg-cyan/10 p-3 text-sm text-text-primary">
                <p class="font-semibold">A imagem precisa ter:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    <li><strong>Formato WebP</strong> (arquivo .webp) — PNG e JPG não são aceitos.</li>
                    <li><strong>Largura:</strong> de <?= $upMinW ?> a <?= $upMaxW ?> pixels.</li>
                    <li><strong>Altura:</strong> na proporção <?= $upRatio ?> (paisagem). Exemplos: <strong><?= $upMinW ?> × <?= $upMinH ?> px</strong> ou <strong>1600 × 900 px</strong>.</li>
                    <li><strong>Peso:</strong> até <?= $upMb ?> MB.</li>
                </ul>
            </div>

            <form method="post" action="<?= $base ?>/images/upload" enctype="multipart/form-data" class="mt-4 space-y-4" data-own-image-form>
                <?= Csrf::field() ?>
                <fieldset>
                    <legend class="text-sm font-medium text-text-primary">Onde usar esta imagem?</legend>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        <?php foreach ([
                            ['FEATURED', 'Imagem destacada', 'A capa do artigo. Já fica escolhida como a destacada.', true],
                            ['BODY', 'Corpo do artigo', 'Entra no meio do texto, distribuída automaticamente.', false],
                        ] as [$val, $label, $hint, $checked]): ?>
                            <label class="relative block cursor-pointer">
                                <input type="radio" name="role" value="<?= $val ?>" <?= $checked ? 'checked' : '' ?> class="peer sr-only" />
                                <?php // .pick = seleção padrão do app (holofote que segue o mouse, brilho ao marcar) — ver input.css ?>
                                <span class="pick flex h-full items-start gap-3 rounded-lg p-3
                                             [&_.own-dot]:border-text-muted peer-checked:[&_.own-dot]:border-cyan peer-checked:[&_.own-dot]:bg-cyan">
                                    <span class="own-dot mt-0.5 h-4 w-4 shrink-0 rounded-full border-2 transition" aria-hidden="true"></span>
                                    <span>
                                        <span class="block text-sm font-semibold text-text-primary"><?= $label ?></span>
                                        <span class="mt-0.5 block text-xs text-text-muted"><?= $hint ?></span>
                                    </span>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div>
                    <span class="block text-sm font-medium text-text-primary">Arquivo da imagem</span>
                    <?php // Mesmo padrão da área de foto do perfil (profile/edit.php): input peer + label .pick tracejado. ?>
                    <input id="own-image" type="file" name="image" accept="image/webp,.webp" required class="peer sr-only" data-own-file />
                    <label for="own-image" data-own-drop
                           class="pick mt-2 flex flex-col items-center justify-center gap-2 rounded-xl border-dashed px-4 py-7 text-center
                                  peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-cyan">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-cyan/15 text-cyan [&>svg]:h-5 [&>svg]:w-5" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                        </span>
                        <span class="text-sm font-semibold text-text-primary" data-own-drop-title>Clique para escolher o arquivo .webp</span>
                        <span class="text-xs text-text-muted" data-own-drop-hint>ou arraste e solte aqui</span>
                    </label>
                    <div class="mt-3 hidden items-center gap-3 rounded-md border border-border bg-surface p-2" data-own-preview>
                        <img alt="" class="h-16 w-28 shrink-0 rounded object-cover" data-own-thumb />
                        <p class="text-sm text-text-secondary" data-own-check></p>
                    </div>
                </div>

                <div>
                    <label for="own-alt" class="block text-sm font-medium text-text-primary">Descrição da imagem (alt)</label>
                    <input id="own-alt" type="text" name="alt_text" maxlength="500" required
                           placeholder="Ex.: Mulher aplicando sérum no rosto em frente ao espelho"
                           class="mt-1 block w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text-primary" />
                    <p class="mt-1 text-xs text-text-muted">Descreva o que aparece na imagem; se fizer sentido, inclua a palavra-chave do artigo.</p>
                </div>

                <button type="submit" class="btn btn-primary inline-flex items-center gap-2 px-4 py-2 text-sm" data-own-submit>
                    <span data-own-submit-icon class="[&>svg]:h-4 [&>svg]:w-4"><?= Icon::nav('plus') ?></span>
                    <span data-own-submit-label>Enviar imagem</span>
                </button>
            </form>
            <script>
            (function () {
                var form = document.querySelector('[data-own-image-form]');
                if (!form) { return; }
                var input = form.querySelector('[data-own-file]');
                var drop = form.querySelector('[data-own-drop]');
                var title = form.querySelector('[data-own-drop-title]');
                var hint = form.querySelector('[data-own-drop-hint]');
                var preview = form.querySelector('[data-own-preview]');
                var thumb = form.querySelector('[data-own-thumb]');
                var check = form.querySelector('[data-own-check]');
                var MIN_W = <?= (int) $upMinW ?>, MAX_W = <?= (int) $upMaxW ?>,
                    RW = <?= (int) ImageUploadValidator::RATIO_W ?>, RH = <?= (int) ImageUploadValidator::RATIO_H ?>,
                    MAX_B = <?= (int) ImageUploadValidator::MAX_BYTES ?>;

                // Confere o arquivo no navegador só pra avisar na hora — quem decide
                // de verdade é o servidor (ImageUploadValidator).
                function show(file) {
                    if (!file) {
                        preview.classList.add('hidden'); preview.classList.remove('flex');
                        title.textContent = 'Clique para escolher o arquivo .webp';
                        hint.textContent = 'ou arraste e solte aqui';
                        return;
                    }
                    title.textContent = file.name;
                    hint.textContent = (file.size < 1048576 ? Math.max(1, Math.round(file.size / 1024)) + ' KB' : (file.size / 1048576).toFixed(2).replace('.', ',') + ' MB') + ' — clique para trocar';
                    preview.classList.remove('hidden'); preview.classList.add('flex');
                    check.textContent = 'Lendo a imagem…';
                    var url = URL.createObjectURL(file);
                    thumb.onload = function () {
                        var w = thumb.naturalWidth, h = thumb.naturalHeight, problems = [];
                        if (!/\.webp$/i.test(file.name) && file.type !== 'image/webp') { problems.push('não é WebP'); }
                        if (w < MIN_W || w > MAX_W) { problems.push('largura fora de ' + MIN_W + '–' + MAX_W + ' px'); }
                        if (Math.abs((w / h) / (RW / RH) - 1) > 0.02) { problems.push('proporção diferente de ' + RW + ':' + RH); }
                        if (file.size > MAX_B) { problems.push('acima de ' + (MAX_B / 1048576) + ' MB'); }
                        check.innerHTML = '<strong class="text-text-primary">' + w + ' × ' + h + ' px</strong> — ' + (problems.length
                            ? '<span class="text-danger">' + problems.join('; ') + '. Ajuste antes de enviar.</span>'
                            : '<span class="text-success">dentro das regras ✓</span>');
                        URL.revokeObjectURL(url);
                    };
                    thumb.onerror = function () { check.innerHTML = '<span class="text-danger">Não foi possível ler esta imagem.</span>'; };
                    thumb.src = url;
                }

                input.addEventListener('change', function () { show(input.files[0]); });
                ['dragenter', 'dragover'].forEach(function (ev) {
                    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('border-cyan', 'bg-cyan/10'); });
                });
                ['dragleave', 'drop'].forEach(function (ev) {
                    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('border-cyan', 'bg-cyan/10'); });
                });
                drop.addEventListener('drop', function (e) {
                    if (e.dataTransfer && e.dataTransfer.files.length) { input.files = e.dataTransfer.files; show(input.files[0]); }
                });

                form.addEventListener('submit', function () {
                    form.querySelector('[data-own-submit]').disabled = true;
                    form.querySelector('[data-own-submit-icon]').innerHTML = '<span class="spinner"></span>';
                    form.querySelector('[data-own-submit-label]').textContent = 'Enviando…';
                });
            })();
            </script>
        </div>
    </section>
    <script>
    // Roda girando sobre cada imagem que ainda está carregando (lazy ou rede lenta).
    document.querySelectorAll('#imagens img').forEach(function (img) {
        if (img.complete && img.naturalWidth > 0) { return; }
        var holder = img.parentElement;
        holder.style.position = 'relative';
        holder.style.minHeight = '9rem';
        var spin = document.createElement('span');
        spin.className = 'spinner img-spinner';
        spin.setAttribute('aria-hidden', 'true');
        holder.appendChild(spin);
        var done = function () { spin.remove(); holder.style.minHeight = ''; };
        img.addEventListener('load', done);
        img.addEventListener('error', done);
    });
    </script>
<?php endif; ?>

<?php
// --- Detalhes técnicos ----------------------------------------------------
// Passos da IA e Fontes eram duas seções soltas de mesmo peso visual que
// tudo acima — juntas aqui, no fim, deixam claro que são apoio/auditoria,
// não parte do fluxo de decisão.
$execStatusBadge = static function (string $status): string {
    [$cls, $label] = match ($status) {
        'SUCCESS' => ['border-success/40 bg-success/15 text-success', 'concluído'],
        'FAILED'  => ['border-danger/40 bg-danger/15 text-danger', 'falhou'],
        default   => ['border-border bg-surface-2 text-text-secondary', $status],
    };
    return '<span class="rounded-full border px-2 py-0.5 text-xs font-medium ' . $cls . '">' . View::e($label) . '</span>';
};
?>
<section class="mt-6 rounded-xl border border-border bg-surface p-5">
    <?php $sectionHeading(Icon::nav('config'), 'Detalhes técnicos'); ?>

    <details class="mt-3 overflow-hidden rounded-lg border border-border">
        <summary class="px-4 py-3 text-sm font-semibold text-text-primary hover:text-cyan">
            Passos da IA (<?= count($executions) ?>)
        </summary>
        <ul class="divide-y divide-border border-t border-border text-sm">
            <?php foreach ($executions as $e): ?>
                <li class="flex items-center justify-between gap-4 px-4 py-2.5">
                    <span class="text-text-primary"><?= View::e($e['step']) ?></span>
                    <span class="flex items-center gap-3 text-xs text-text-muted">
                        <?php if ((int) $e['retry_count'] > 0): ?><span><?= View::e($e['retry_count']) ?> retry</span><?php endif; ?>
                        <span>US$ <?= number_format((float) $e['cost'], 4) ?></span>
                        <?= $execStatusBadge((string) $e['status']) ?>
                    </span>
                </li>
                <?php if (!empty($e['error_message'])): ?>
                    <li class="bg-danger/5 px-4 py-2 text-xs text-danger"><?= View::e($e['error_message']) ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </details>

    <?php if ($sources !== []): ?>
        <details class="mt-3 overflow-hidden rounded-lg border border-border">
            <summary class="px-4 py-3 text-sm font-semibold text-text-primary hover:text-cyan">
                Fontes (<?= count($sources) ?>)
            </summary>
            <ul class="divide-y divide-border border-t border-border text-sm">
                <?php foreach ($sources as $s): ?>
                    <li class="px-4 py-2.5">
                        <a href="<?= View::e($s['url']) ?>" target="_blank" rel="noopener"
                           class="text-cyan hover:text-cyan-bright"><?= View::e($s['title'] ?: $s['url']) ?></a>
                        <?php if (!empty($s['publisher'])): ?><span class="text-text-muted"> — <?= View::e($s['publisher']) ?></span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>
    <?php endif; ?>
</section>

<section class="mt-8">
    <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-text-muted">
        Corpo <?php if ($version && $version['word_count']): ?><span class="text-text-muted">(<?= View::e($version['word_count']) ?> palavras)</span><?php endif; ?>
    </h3>
    <?php if ($version === null): ?>
        <p class="mt-2 text-text-secondary">Ainda sem corpo — a escrita não chegou a rodar.</p>
    <?php else: ?>
        <article class="article-body mt-3 max-w-none rounded-lg border border-border p-6">
            <?= $version['content'] /* HTML gerado pela IA (ou editado pelo Redator-Chefe) — renderizado como veio */ ?>
        </article>
        <?php if ($article['status'] === 'IN_REVIEW'): ?>
            <?php
            $linkSnippet = static function (string $href, string $text, bool $external = false): string {
                $attrs = $external ? ' target="_blank" rel="noopener"' : '';
                return '<a href="' . $href . '"' . $attrs . '>' . $text . '</a>';
            };
            ?>
            <section id="editar-corpo" class="mt-6 overflow-hidden rounded-lg border border-border bg-surface">
                <details>
                    <summary class="cursor-pointer px-5 py-4 text-sm font-semibold text-text-primary hover:text-cyan">
                        Editar corpo
                    </summary>
                    <div class="space-y-6 border-t border-border px-5 py-6">
                        <p class="text-sm text-text-secondary">
                            Edite o texto direto, como no editor do WordPress — sem precisar entender HTML. Pra
                            linkar algo, posicione o cursor onde quer o link e clique em "Inserir" num dos itens
                            abaixo (interno, externo, ou em branco pra um link seu).
                        </p>

                        <?php
                        /** @var list<array{id:int, title:string, wordpress_post_id:int, reason:string}> $internalSuggestions */
                        $internalSuggestions = $notes['pipeline']['internal_link_suggestions'] ?? [];
                        ?>
                        <div class="grid gap-5 lg:grid-cols-2">
                            <div class="rounded-lg border border-border bg-surface-2/40 p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-text-primary">Links internos disponíveis</p>
                                        <p class="mt-1 text-xs text-text-muted">
                                            <?= $internalSuggestions !== []
                                                ? 'Escolhidos pela IA como relevantes pro conteúdo deste artigo.'
                                                : 'Todos os artigos publicados, do mais recente pro mais antigo — sem filtro de relevância. Confira se o assunto tem a ver antes de usar, ou clique em "Sugerir por relevância".' ?>
                                        </p>
                                    </div>
                                    <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($article['id']) ?>/internal-links/suggest"
                                          onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Analisando…';">
                                        <?= Csrf::field() ?>
                                        <button type="submit"
                                                class="btn btn-primary shrink-0 whitespace-nowrap px-2.5 py-1.5 text-[11px]">
                                            <?= $internalSuggestions !== [] ? 'Atualizar sugestões' : 'Sugerir por relevância' ?>
                                        </button>
                                    </form>
                                </div>
                                <?php if ($internalSuggestions !== []): ?>
                                    <ul class="mt-3 max-h-72 space-y-2 overflow-y-auto pr-1">
                                        <?php foreach ($internalSuggestions as $c):
                                            $url = rtrim((string) ($site['wordpress_url'] ?? ''), '/') . '/?p=' . $c['wordpress_post_id'];
                                            $snippet = $linkSnippet($url, $c['title']);
                                        ?>
                                            <li class="rounded-md border border-border bg-surface p-3">
                                                <p class="truncate text-xs font-medium text-text-primary" title="<?= View::e($c['title']) ?>"><?= View::e($c['title']) ?></p>
                                                <?php if ($c['reason'] !== ''): ?>
                                                    <p class="mt-0.5 text-[11px] text-text-muted"><?= View::e($c['reason']) ?></p>
                                                <?php endif; ?>
                                                <div class="mt-2 flex items-center gap-2">
                                                    <code class="min-w-0 flex-1 truncate rounded bg-surface-2 px-2 py-1.5 text-[11px] text-text-secondary"><?= View::e($snippet) ?></code>
                                                    <button type="button" data-insert-link="<?= View::e($snippet) ?>"
                                                            class="btn btn-secondary shrink-0 px-2.5 py-1.5 text-[11px]">
                                                        Inserir
                                                    </button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php elseif ($internalCandidates === []): ?>
                                    <p class="mt-3 text-xs text-text-muted">Nenhum artigo publicado ainda pra linkar.</p>
                                <?php else: ?>
                                    <ul class="mt-3 max-h-72 space-y-2 overflow-y-auto pr-1">
                                        <?php foreach ($internalCandidates as $c):
                                            $snippet = $linkSnippet($c['link'], $c['title']);
                                        ?>
                                            <li class="rounded-md border border-border bg-surface p-3">
                                                <p class="truncate text-xs font-medium text-text-primary" title="<?= View::e($c['title']) ?>"><?= View::e($c['title']) ?></p>
                                                <div class="mt-2 flex items-center gap-2">
                                                    <code class="min-w-0 flex-1 truncate rounded bg-surface-2 px-2 py-1.5 text-[11px] text-text-secondary"><?= View::e($snippet) ?></code>
                                                    <button type="button" data-insert-link="<?= View::e($snippet) ?>"
                                                            class="btn btn-secondary shrink-0 px-2.5 py-1.5 text-[11px]">
                                                        Inserir
                                                    </button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                            <div class="rounded-lg border border-border bg-surface-2/40 p-4">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold text-text-primary">Fontes externas desta pesquisa</p>
                                        <p class="mt-1 text-xs text-text-muted">Fontes que a pesquisa da IA já encontrou pra este artigo.</p>
                                    </div>
                                    <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($article['id']) ?>/sources/suggest"
                                          onsubmit="var b=this.querySelector('button');b.disabled=true;b.innerHTML='<span class=&quot;spinner&quot;></span> Gerando…';">
                                        <?= Csrf::field() ?>
                                        <button type="submit"
                                                class="btn btn-primary shrink-0 whitespace-nowrap px-2.5 py-1.5 text-[11px]">
                                            Gerar mais
                                        </button>
                                    </form>
                                </div>
                                <?php if ($sources === []): ?>
                                    <p class="mt-3 text-xs text-text-muted">Nenhuma fonte registrada pra este artigo.</p>
                                <?php else: ?>
                                    <ul class="mt-3 max-h-72 space-y-2 overflow-y-auto pr-1">
                                        <?php foreach ($sources as $s):
                                            $label = (string) ($s['title'] ?: $s['url']);
                                            $snippet = $linkSnippet((string) $s['url'], $label, external: true);
                                        ?>
                                            <li class="rounded-md border border-border bg-surface p-3">
                                                <p class="truncate text-xs font-medium text-text-primary" title="<?= View::e($label) ?>">
                                                    <?= View::e($label) ?>
                                                    <?php if (!empty($s['publisher'])): ?><span class="font-normal text-text-muted">— <?= View::e($s['publisher']) ?></span><?php endif; ?>
                                                </p>
                                                <div class="mt-2 flex items-center gap-2">
                                                    <code class="min-w-0 flex-1 truncate rounded bg-surface-2 px-2 py-1.5 text-[11px] text-text-secondary"><?= View::e($snippet) ?></code>
                                                    <button type="button" data-insert-link="<?= View::e($snippet) ?>"
                                                            class="btn btn-secondary shrink-0 px-2.5 py-1.5 text-[11px]">
                                                        Inserir
                                                    </button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="rounded-lg border border-border bg-surface-2/40 p-4">
                            <p class="text-sm font-semibold text-text-primary">Link personalizado</p>
                            <p class="mt-1 text-xs text-text-muted">
                                Quer linkar algo que não está em nenhum painel acima? Selecione o texto no corpo
                                abaixo (ou só posicione o cursor) e clique aqui — abre uma janela pra você colar a
                                URL de destino.
                            </p>
                            <button type="button" data-open-link-dialog
                                    class="btn btn-primary mt-3 shrink-0 px-2.5 py-1.5 text-[11px]">
                                Inserir link personalizado
                            </button>
                        </div>

                        <form method="post" action="/sites/<?= View::e($site['id']) ?>/production/<?= View::e($article['id']) ?>/content" id="editar-corpo-form">
                            <?= Csrf::field() ?>
                            <label class="text-sm">
                                <span class="block font-medium text-text-secondary">Corpo do artigo</span>
                                <textarea name="content_html" id="content-html-editor" required
                                          class="mt-2 w-full rounded-md border border-border bg-surface-2 px-4 py-3 text-sm text-text-primary"
                                ><?= View::e((string) $version['content']) ?></textarea>
                            </label>
                            <p class="mt-2 text-xs text-text-muted">
                                Edite como um texto normal — negrito, títulos, listas e links funcionam pelos botões
                                acima do texto, igual num editor de WordPress. Vira uma nova versão ao salvar — a
                                anterior fica preservada no histórico.
                            </p>

                            <button type="submit" class="btn btn-primary mt-4 px-4 py-2 text-sm">
                                Salvar corpo
                            </button>
                        </form>
                    </div>
                </details>
            </section>

            <script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin" defer></script>
            <script src="https://cdn.jsdelivr.net/npm/tinymce-i18n@24/langs6/pt_BR.js" referrerpolicy="origin" defer></script>
            <script src="<?= View::e(View::asset('assets/js/rich-editor.js')) ?>" defer></script>
        <?php endif; ?>
    <?php endif; ?>
</section>
