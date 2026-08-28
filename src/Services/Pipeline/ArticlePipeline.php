<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Integrations\AIException;
use App\Integrations\AIProvider;
use App\Integrations\AIResult;
use App\Integrations\Gemini\GeminiProvider;
use App\Queue\RetryPolicy;
use App\Queue\RetryRunner;
use App\Services\AiExecutionService;
use App\Services\ArticleNoteService;
use App\Services\ArticleService;
use App\Services\CategoryService;
use App\Services\PromptBuilder;
use Throwable;

/**
 * Orquestra a produção de um rascunho (fluxo-editorial §21):
 *
 *   cria artigo (PLANNED)
 *     -> planning    (título, palavra-chave, ângulo; checa canibalização)
 *     -> research    (fatos + fontes -> article_sources)
 *     -> writing     (corpo -> article_versions; artigo vira IN_PROGRESS)
 *     -> seo         (auditoria on-page)
 *     -> compliance  (auditoria de qualidade/AdSense)
 *     -> review      (parecer pré-humano)  -> artigo vira IN_REVIEW
 *
 * Cada passo é uma execução rastreada em `ai_executions` com retry (4.3); o JSON
 * de cada passo é gravado em `article_ai_notes`. A aprovação é sempre humana
 * (requisitos §65.1) — as pendências de seo/compliance/review ficam nas notas.
 */
final class ArticlePipeline
{
    private const PROVIDER = 'gemini';

    private AIProvider $ai;
    private PromptBuilder $prompts;
    private ArticleService $articles;
    private AiExecutionService $executions;
    private ArticleNoteService $notes;
    private CategoryService $categories;
    private RetryRunner $retry;

    public function __construct(
        ?AIProvider $ai = null,
        ?PromptBuilder $prompts = null,
        ?ArticleService $articles = null,
        ?AiExecutionService $executions = null,
        ?ArticleNoteService $notes = null,
        ?CategoryService $categories = null,
        ?RetryRunner $retry = null,
    ) {
        $this->ai = $ai ?? new GeminiProvider();
        $this->prompts = $prompts ?? new PromptBuilder();
        $this->articles = $articles ?? new ArticleService();
        $this->executions = $executions ?? new AiExecutionService();
        $this->notes = $notes ?? new ArticleNoteService();
        $this->categories = $categories ?? new CategoryService();
        // O pipeline roda inline (driver de fila síncrono) — backoff curto.
        // Um worker Redis futuro passaria RetryPolicy::default() aqui.
        $this->retry = $retry ?? new RetryRunner(RetryPolicy::inline());
    }

    /**
     * @return array{article_id:int, title:string, word_count:int, cost:float, recommendation:string, warnings:list<string>}
     * @throws PipelineException
     */
    public function generate(int $siteId, ?int $goalId = null, ?int $categoryId = null): array
    {
        $articleId = $this->articles->create($siteId, $goalId);
        $warnings = [];

        // --- planning ---------------------------------------------------------
        $plan = $this->step('planning', $articleId, $siteId, $goalId, $categoryId);

        $resolvedCategoryId = $categoryId ?? $this->matchCategory($siteId, (string) ($plan['category'] ?? ''));
        if ($resolvedCategoryId === null) {
            $warnings[] = 'Categoria "' . ($plan['category'] ?? '?') . '" não bate com nenhuma cadastrada — artigo sem categoria.';
        }

        $keyword = trim((string) ($plan['focus_keyword'] ?? ''));
        $this->articles->applyPlan($articleId, (string) ($plan['title'] ?? 'Sem título'), $keyword, $resolvedCategoryId);

        if (($plan['cannibalization_risk'] ?? 'none') !== 'none') {
            $note = trim((string) ($plan['cannibalization_note'] ?? ''));
            $warnings[] = 'Canibalização (IA): ' . ($note !== '' ? $note : $plan['cannibalization_risk']);
        }
        if ($keyword !== '' && $this->articles->keywordExists($siteId, $keyword, $articleId)) {
            $warnings[] = 'A palavra-chave "' . $keyword . '" já está em outro artigo deste site.';
        }

        $brief = [
            'title'         => (string) ($plan['title'] ?? ''),
            'focus_keyword' => $keyword,
            'angle'         => (string) ($plan['angle'] ?? ''),
        ];

        // --- research --------------------------------------------------------
        $research = $this->step('research', $articleId, $siteId, $goalId, $resolvedCategoryId, $brief);

        $sourceCount = 0;
        foreach ((array) ($research['findings'] ?? []) as $finding) {
            if (is_array($finding) && trim((string) ($finding['source_url'] ?? '')) !== '') {
                $this->articles->addSource($articleId, $finding);
                $sourceCount++;
            }
        }
        if ($sourceCount === 0) {
            $warnings[] = 'A pesquisa não retornou nenhuma fonte com URL.';
        }
        if ((array) ($research['gaps'] ?? []) !== []) {
            $warnings[] = 'Pesquisa com lacunas: ' . implode('; ', array_map('strval', (array) $research['gaps']));
        }

        // --- writing --------------------------------------------------------
        $writeBrief = $brief + ['notes' => $this->researchDigest($research)];
        $writing = $this->step('writing', $articleId, $siteId, $goalId, $resolvedCategoryId, $writeBrief);

        $html = (string) ($writing['content_html'] ?? '');
        if (trim($html) === '') {
            throw new PipelineException('O passo writing não retornou content_html.', $articleId, 'writing');
        }
        $wordCount = (int) ($writing['word_count'] ?? 0);
        if ($wordCount <= 0) {
            $wordCount = str_word_count(strip_tags($html));
        }

        $this->articles->addVersion($articleId, $html, $wordCount);
        $this->articles->applyWriting(
            $articleId,
            $siteId,
            (string) ($writing['title'] ?? $plan['title'] ?? 'Sem título'),
            (string) ($writing['slug'] ?? ''),
            trim((string) ($writing['focus_keyword'] ?? '')) ?: $keyword,
            (string) ($writing['meta_description'] ?? ''),
        );

        if ($wordCount < 1500) {
            $warnings[] = "Texto com {$wordCount} palavras — abaixo do mínimo de 1500 (seo.md).";
        }
        foreach ((array) ($writing['open_questions'] ?? []) as $q) {
            $warnings[] = 'Pergunta em aberto (IA): ' . (string) $q;
        }

        // --- portões de qualidade (4.4b) -----------------------------------
        $finalTitle = (string) ($writing['title'] ?? $plan['title'] ?? 'Sem título');
        $draft = [
            'title'           => $finalTitle,
            'slug'            => (string) ($writing['slug'] ?? ''),
            'meta_description' => (string) ($writing['meta_description'] ?? ''),
            'word_count'      => $wordCount,
            'content_html'    => $html,
        ];

        // --- seo -----------------------------------------------------------
        $seo = $this->step('seo', $articleId, $siteId, $goalId, $resolvedCategoryId, $brief, $draft);
        foreach ((array) ($seo['issues'] ?? []) as $issue) {
            if (is_array($issue) && ($issue['severity'] ?? '') === 'block') {
                $warnings[] = 'SEO (bloqueio): ' . (string) ($issue['item'] ?? '') . ' — ' . (string) ($issue['fix'] ?? '');
            }
        }
        if (($seo['cannibalization'] ?? 'none') === 'high') {
            $warnings[] = 'SEO: risco alto de canibalização de palavra-chave.';
        }

        // --- compliance --------------------------------------------------
        $compliance = $this->step('compliance', $articleId, $siteId, $goalId, $resolvedCategoryId, $brief, $draft);
        foreach ((array) ($compliance['blocking'] ?? []) as $b) {
            if (is_array($b)) {
                $warnings[] = 'Compliance (bloqueio): ' . (string) ($b['rule'] ?? '') . ' — ' . (string) ($b['fix'] ?? $b['evidence'] ?? '');
            }
        }

        // --- review (parecer pré-humano) --------------------------------
        $reviewBrief = $brief + ['notes' => $this->auditDigest($seo, $compliance)];
        $review = $this->step('review', $articleId, $siteId, $goalId, $resolvedCategoryId, $reviewBrief, $draft);
        $recommendation = (string) ($review['recommendation'] ?? 'needs_fix');
        if ($recommendation !== 'ready_for_human') {
            $warnings[] = 'Revisão da IA: ' . $recommendation . ' — ' . (string) ($review['summary'] ?? '');
        }

        // O humano é quem aprova (requisitos §65.1) — o artigo entra na fila de revisão
        // mesmo com pendências; elas ficam visíveis nas notas.
        $this->articles->setStatus($articleId, 'IN_REVIEW');

        return [
            'article_id'     => $articleId,
            'title'          => $finalTitle,
            'word_count'     => $wordCount,
            'cost'           => $this->executions->totalCostForArticle($articleId),
            'recommendation' => $recommendation,
            'warnings'       => $warnings,
        ];
    }

    /**
     * @param array<string, mixed> $seo
     * @param array<string, mixed> $compliance
     */
    private function auditDigest(array $seo, array $compliance): string
    {
        $lines = [];
        $lines[] = 'SEO passa: ' . (($seo['passes'] ?? false) ? 'sim' : 'não');
        foreach ((array) ($seo['issues'] ?? []) as $i) {
            if (is_array($i)) {
                $lines[] = '- SEO [' . (string) ($i['severity'] ?? '?') . '] ' . (string) ($i['item'] ?? '');
            }
        }
        $lines[] = 'Compliance aprova: ' . (($compliance['approved'] ?? false) ? 'sim' : 'não');
        foreach ((array) ($compliance['blocking'] ?? []) as $b) {
            if (is_array($b)) {
                $lines[] = '- Compliance bloqueio: ' . (string) ($b['rule'] ?? '');
            }
        }

        return "Resultado das auditorias:\n" . implode("\n", $lines);
    }

    /**
     * Roda um passo: monta o prompt, chama a IA com retry, registra em ai_executions.
     *
     * @param array<string, string> $brief
     * @param array<string, mixed>  $draft  artigo já escrito (passos de auditoria)
     * @return array<string, mixed> o JSON da resposta
     * @throws PipelineException
     */
    private function step(string $step, int $articleId, int $siteId, ?int $goalId, ?int $categoryId, array $brief = [], array $draft = []): array
    {
        $execId = $this->executions->create($articleId, $step, self::PROVIDER);
        $this->executions->markRunning($execId);

        try {
            $prompt = $this->prompts->build($step, $siteId, $goalId, $categoryId, $brief, $draft);
            $result = $this->retry->run(
                fn (): AIResult => $this->ai->generateJson($prompt, StepSchemas::{$step}()),
                fn (int $attempt, AIException $e) => $this->executions->markRetrying($execId, $attempt, $e->getMessage()),
            );
        } catch (Throwable $e) {
            $this->executions->markFailed($execId, $e->getMessage());
            throw new PipelineException("Passo {$step} falhou: {$e->getMessage()}", $articleId, $step, $e);
        }

        if ($result->json === null) {
            $this->executions->markFailed($execId, 'resposta da IA não era JSON válido');
            throw new PipelineException("Passo {$step}: resposta da IA não era JSON válido.", $articleId, $step);
        }

        $this->executions->markSuccess($execId, $result);
        $this->notes->save($articleId, $step, $result->json);

        return $result->json;
    }

    private function matchCategory(int $siteId, string $name): ?int
    {
        $name = mb_strtolower(trim($name));
        if ($name === '') {
            return null;
        }
        foreach ($this->categories->allForSite($siteId) as $category) {
            if (mb_strtolower((string) $category['name']) === $name) {
                return (int) $category['id'];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $research */
    private function researchDigest(array $research): string
    {
        $lines = [];
        foreach ((array) ($research['findings'] ?? []) as $f) {
            if (!is_array($f)) {
                continue;
            }
            $claim = trim((string) ($f['claim'] ?? ''));
            $src = trim((string) ($f['source_title'] ?? $f['publisher'] ?? $f['source_url'] ?? ''));
            if ($claim !== '') {
                $lines[] = '- ' . $claim . ($src !== '' ? " (fonte: {$src})" : '');
            }
        }

        return $lines === [] ? '(pesquisa sem fatos utilizáveis)' : "Fatos da pesquisa:\n" . implode("\n", $lines);
    }
}
