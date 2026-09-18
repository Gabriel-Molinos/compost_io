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
<a href="/sites/<?= View::e($site['id']) ?>/production" class="inline-flex items-center gap-1 text-sm text-text-secondary hover:text-text-primary">
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
    Produção
</a>

<div class="mt-3 rounded-lg border border-border bg-surface p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 class="font-display text-xl font-semibold text-text-primary"><?= View::e($article['title'] ?: 'Rascunho #' . $article['id']) ?></h2>
        <?= Labels::articleStatusBadge((string) $article['status']) ?>
    </div>
    <?php if (in_array($article['status'], ['PLANNED', 'IN_PROGRESS'], true)): ?>
        <div class="loading-bar-track mt-3 max-w-xs" role="progressbar" aria-label="A IA está gerando este rascunho">
            <div class="loading-bar-fill"></div>
        </div>
    <?php endif; ?>
    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm text-text-secondary">
        <?php if ((int) ($article['attempt_number'] ?? 1) > 1): ?>
            <span>tentativa <?= View::e($article['attempt_number']) ?></span>
        <?php endif; ?>
        <?php if (!empty($article['focus_keyword'])): ?>
            <span>palavra-chave: <em class="text-text-primary not-italic font-medium"><?= View::e($article['focus_keyword']) ?></em></span>
        <?php endif; ?>
        <span>custo total <span class="text-text-primary font-medium">~US$ <?= number_format($totalCost, 4) ?></span></span>
    </div>
    <?php if (!empty($article['meta_description'])): ?>
        <p class="mt-3 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-text-secondary">
            <span class="font-semibold text-text-primary">Meta descrição:</span> <?= View::e($article['meta_description']) ?>
        </p>
    <?php endif; ?>
</div>

<?php
$alertIcon = static function (string $kind): string {
    $path = $kind === 'danger'
        ? '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>'
        : '<circle cx="12" cy="12" r="9"/><path d="M12 8v5"/><path d="M12 16h.01"/>';
    return '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};
?>
<?php $linkRotAt = $notes['pipeline']['link_rot_detected_at'] ?? null; ?>
<?php if ($linkRotAt !== null): ?>
    <div role="alert" class="mt-4 flex gap-3 rounded-lg border border-danger/40 bg-danger/10 p-4">
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
    <div role="alert" class="mt-4 flex gap-3 rounded-lg border border-warning/40 bg-warning/10 p-4">
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

<?php if (in_array($article['status'], ['PLANNED', 'IN_PROGRESS'], true)): ?>
    <p role="status" class="mt-4 flex items-center gap-2 rounded-lg border border-border bg-surface px-4 py-3 text-sm text-text-secondary">
        <span aria-hidden="true" class="status-dot text-cyan">●</span>
        Gerando em segundo plano — a página atualiza sozinha.
    </p>
<?php elseif ($article['status'] === 'ERROR'): ?>
    <?php
    $lastFailure = null;
    foreach (array_reverse($executions) as $execution) {
        if ($execution['status'] === 'FAILED') {
            $lastFailure = $execution;
            break;
        }
    }
    ?>
    <div role="alert" class="mt-4 flex gap-3 rounded-lg border border-danger/40 bg-danger/10 p-4">
        <span class="shrink-0 text-danger"><?= $alertIcon('danger') ?></span>
        <p class="text-sm text-text-primary">
            <span class="font-semibold text-danger">Falha técnica na geração<?= $lastFailure !== null ? ' (passo: ' . View::e($lastFailure['step']) . ')' : '' ?>.</span>
            <?= $lastFailure !== null && $lastFailure['error_message'] ? View::e($lastFailure['error_message']) : 'Sem detalhe registrado.' ?>
        </p>
    </div>
<?php endif; ?>

<?php if ($article['status'] === 'IN_REVIEW'): ?>
    <?php $checklistPassed = $checklist !== null && \App\Services\ArticleReviewService::checklistPassed($checklist); ?>
    <section class="mt-8">
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-text-muted">Checklist de pré-aprovação</h3>
        <p class="mt-1 text-xs text-text-muted">Obrigatório — aprovar só é permitido se os 3 itens estiverem ok.</p>
        <ul class="mt-3 grid gap-2 sm:grid-cols-3">
            <?php foreach ($checklist as $item): ?>
                <li class="flex items-start gap-2 rounded-lg border p-3 text-sm <?= $item['ok'] ? 'border-success/40 bg-success/5' : 'border-danger/40 bg-danger/5' ?>">
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
    </section>

    <section data-tour="review-actions" class="mt-4">
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-text-muted">Revisão</h3>
        <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-success/40 bg-success/5 p-4">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-success/15 text-success" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5 9.5 18 20 6"/></svg>
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
                    <button type="submit" <?= $checklistPassed ? '' : 'disabled title="Checklist de pré-aprovação não passou"' ?>
                            class="btn btn-success px-4 py-2 text-sm">
                        Aprovar artigo
                    </button>
                </form>
            </div>

            <div class="rounded-lg border border-danger/40 bg-danger/5 p-4">
                <div class="flex items-center gap-2">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-danger/15 text-danger" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6 6 18"/></svg>
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
                            <select name="reason" required
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
    <section id="agendar" class="mt-8 rounded-lg border border-border bg-surface p-4">
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-text-muted">Agendar publicação</h3>
        <?php if ($authors === []): ?>
            <p class="mt-1 text-sm text-text-secondary">
                Nenhum autor disponível. Sincronize os autores em
                <a href="/sites/<?= View::e($site['id']) ?>/wordpress" class="text-cyan hover:text-cyan-bright">WordPress</a>.
            </p>
        <?php elseif ($featuredSelected === null): ?>
            <p class="mt-1 text-sm text-text-secondary">
                Escolha a <a href="#imagens" class="text-cyan hover:text-cyan-bright">imagem destacada</a> antes de agendar.
            </p>
        <?php else: ?>
            <p class="mt-1 text-sm text-text-secondary">
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
    <section id="agendar" class="mt-8 rounded-lg border border-cyan/40 bg-surface p-4">
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-text-muted">Agendado</h3>
        <p class="mt-1 text-sm text-text-primary">
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
    <section id="agendar" class="mt-8 rounded-lg border border-danger/40 bg-danger/10 p-4">
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-danger">Falha no envio ao WordPress</h3>
        <p role="alert" class="mt-1 text-sm text-text-secondary">
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
    <section id="agendar" class="mt-8 rounded-lg border border-success/40 bg-surface p-4">
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-text-muted">Publicado</h3>
        <p class="mt-1 text-sm text-text-primary">
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
    <section class="mt-8 rounded-lg border border-border bg-surface p-4">
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-text-muted">Regeneração</h3>
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
              onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Iniciando…';">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-primary px-4 py-2 text-sm">
                Regenerar artigo
            </button>
        </form>
    </section>
<?php elseif ($article['status'] === 'ERROR'): ?>
    <section class="mt-8 rounded-lg border border-border bg-surface p-4">
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-text-muted">Tentar de novo</h3>
        <p class="mt-1 text-sm text-text-secondary">
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
    <section class="mt-8 rounded-lg border border-danger/40 bg-danger/10 p-4">
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-danger">Bloqueado</h3>
        <p class="mt-1 text-sm text-text-secondary">
            O limite de <?= $maxAttempts ?> tentativas nesta linhagem foi atingido (rejeição e/ou falha técnica), sem aprovação. Decida o próximo passo — descartar, ou revisar a meta/diretrizes do site antes de tentar um novo tema.
        </p>
    </section>
<?php endif; ?>

<?php if (!empty($feedback)): ?>
    <section class="mt-8 rounded-lg border border-border bg-surface p-4">
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-text-muted">Feedback de rejeição (<?= count($feedback) ?>)</h3>
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

<?php
$execStatusBadge = static function (string $status): string {
    [$cls, $label] = match ($status) {
        'SUCCESS' => ['border-success/40 bg-success/15 text-success', 'concluído'],
        'FAILED'  => ['border-danger/40 bg-danger/15 text-danger', 'falhou'],
        default   => ['border-border bg-surface-2 text-text-secondary', $status],
    };
    return '<span class="rounded-full border px-2 py-0.5 text-xs font-medium ' . $cls . '">' . View::e($label) . '</span>';
};
?>
<section class="mt-8 overflow-hidden rounded-lg border border-border bg-surface">
    <details>
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
</section>

<?php if ($sources !== []): ?>
    <section class="mt-8 overflow-hidden rounded-lg border border-border bg-surface">
        <details>
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
    </section>
<?php endif; ?>

<?php if (!empty($images)): ?>
    <?php
    $featured = array_values(array_filter($images, static fn ($i) => $i['role'] === 'FEATURED'));
    $body = array_values(array_filter($images, static fn ($i) => $i['role'] === 'BODY'));
    $base = '/sites/' . View::e($site['id']) . '/production/' . View::e($article['id']);
    $deleteForm = static function (array $img) use ($base): void {
        echo '<form method="post" action="' . $base . '/images/' . View::e($img['id']) . '/delete"'
            . ' data-confirm="Remover esta imagem?" class="mt-3">';
        echo Csrf::field();
        echo '<button type="submit" class="btn btn-danger flex w-full items-center justify-center gap-1.5 px-3 py-1.5 text-xs">'
            . '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/>'
            . '</svg>Remover imagem</button>';
        echo '</form>';
    };
    ?>
    <section id="imagens" class="mt-8">
        <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-text-muted">
            Imagens (<?= count($images) ?>)
        </h3>

        <?php if ($featured !== []): ?>
            <p class="mt-3 text-sm font-medium text-text-primary">Imagem destacada — <?= count($featured) ?> opção(ões)</p>
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
                                    <span class="font-normal opacity-75">· <?= View::e($i['format'] ?? '') ?></span>
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
            <p class="mt-5 text-sm font-medium text-text-primary">Imagens do corpo — <?= count($body) ?></p>
            <p class="text-xs text-text-muted">Distribuídas automaticamente pelo texto na publicação.</p>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($body as $i): ?>
                    <figure class="hover-card overflow-hidden rounded-lg border border-border bg-surface p-2">
                        <span class="relative block overflow-hidden rounded">
                            <img src="<?= View::e($i['url']) ?>" alt="<?= View::e($i['alt_text'] ?? '') ?>" loading="lazy" class="w-full rounded" />
                            <button type="button"
                                    data-lightbox="<?= View::e($i['url']) ?>"
                                    aria-label="Ver imagem em tamanho grande"
                                    class="absolute inset-0 flex items-center justify-center bg-[#050B0F]/40 opacity-0 transition hover:opacity-100 focus-visible:opacity-100">
                                <span class="rounded-md bg-surface/90 px-3 py-1.5 text-sm font-medium text-text-primary">Ver ampliado</span>
                            </button>
                        </span>
                        <figcaption class="mt-2 text-xs font-medium text-text-secondary">
                            Imagem de corpo <span class="font-normal text-text-muted">· <?= View::e($i['format'] ?? '') ?></span>
                        </figcaption>
                        <?php if (!empty($i['alt_text'])): ?>
                            <p class="mt-2 rounded-md border border-border bg-surface-2 px-3 py-2 text-sm text-text-primary">
                                <span class="font-semibold text-text-secondary">Descrição da imagem (alt):</span>
                                <?= View::e($i['alt_text']) ?>
                            </p>
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
<?php
$recommendationBadge = static function (string $rec): string {
    [$cls, $label] = match ($rec) {
        'ready_for_human' => ['border-success/40 bg-success/15 text-success', 'pronto pra revisão'],
        'needs_fix'        => ['border-warning/40 bg-warning/15 text-warning', 'precisa de ajuste'],
        default            => ['border-border bg-surface-2 text-text-secondary', $rec],
    };
    return '<span class="rounded-full border px-2.5 py-1 text-xs font-semibold ' . $cls . '">' . View::e($label) . '</span>';
};
?>
<?php if ($review !== null): ?>
    <section class="mt-8 rounded-lg border border-border bg-surface p-4">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="font-display text-sm font-semibold uppercase tracking-wide text-text-muted">Parecer da IA</h3>
            <?= $recommendationBadge((string) ($review['recommendation'] ?? '')) ?>
        </div>
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
    </section>
<?php endif; ?>

<?php
/** @param list<array{text:string, severity:string}> $items */
$gate = static function (string $label, ?array $note, bool $ok, array $items) : void {
    if ($note === null) { return; }
    $badgeCls = $ok ? 'border-success/40 bg-success/15 text-success' : 'border-danger/40 bg-danger/15 text-danger';
    $badgeLabel = $ok ? 'ok' : 'com pendências';
    echo '<div class="rounded-lg border border-border bg-surface p-4">';
    echo '<div class="flex items-center justify-between gap-2">'
        . '<span class="font-display text-sm font-semibold uppercase tracking-wide text-text-muted">' . View::e($label) . '</span>'
        . '<span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold ' . $badgeCls . '">' . $badgeLabel . '</span>'
        . '</div>';
    if ($items !== []) {
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
                . '<span class="text-text-primary">' . View::e($it['text']) . '</span></li>';
        }
        echo '</ul>';
    }
    echo '</div>';
};
$seoItems = [];
foreach ((array) ($seo['issues'] ?? []) as $i) {
    if (!is_array($i)) { continue; }
    $seoItems[] = ['severity' => (string) ($i['severity'] ?? '?'), 'text' => ($i['item'] ?? '') . (empty($i['fix']) ? '' : ' → ' . $i['fix'])];
}
$compItems = [];
foreach ((array) ($compliance['blocking'] ?? []) as $b) {
    if (!is_array($b)) { continue; }
    $compItems[] = ['severity' => 'block', 'text' => ($b['rule'] ?? '') . (empty($b['fix']) ? '' : ' → ' . $b['fix'])];
}
foreach ((array) ($compliance['warnings'] ?? []) as $w) {
    if (!is_array($w)) { continue; }
    $compItems[] = ['severity' => 'aviso', 'text' => ($w['rule'] ?? '') . (empty($w['note']) ? '' : ': ' . $w['note'])];
}
?>
<?php if ($seo !== null || $compliance !== null): ?>
    <section class="mt-4 grid gap-3 sm:grid-cols-2">
        <?php $gate('SEO', $seo, (bool) ($seo['passes'] ?? false), $seoItems); ?>
        <?php $gate('Compliance', $compliance, (bool) ($compliance['approved'] ?? false), $compItems); ?>
    </section>
<?php endif; ?>

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
                                          onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Gerando…';">
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
