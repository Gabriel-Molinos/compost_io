<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Config\Env;
use App\Integrations\AIException;
use App\Integrations\AIProvider;
use App\Integrations\AIResult;
use App\Integrations\Gemini\GeminiProvider;
use App\Integrations\Image\ImagePricing;
use App\Integrations\Image\ImageProvider;
use App\Integrations\Image\ImageRequest;
use App\Integrations\Image\NanoBanana\NanoBananaProvider;
use App\Integrations\WordPress\ExternalLinkVerifier;
use App\Integrations\WordPress\InternalLinkResolver;
use App\Integrations\WordPress\WordPressException;
use App\Queue\RetryPolicy;
use App\Queue\RetryRunner;
use App\Services\AiExecutionService;
use App\Services\ArticleNoteService;
use App\Services\ArticleReviewService;
use App\Services\ArticleService;
use App\Services\CategoryService;
use App\Services\EditorialMemoryService;
use App\Services\FeedbackService;
use App\Services\ImageService;
use App\Services\NotificationService;
use App\Services\PromptBuilder;
use App\Services\WordPressConnectionService;
use App\Support\ImageConverter;
use App\Support\ImageStorage;
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
 *
 * Cada fluxo (geração / regeneração) é dividido em `prepareX()` (síncrono,
 * rápido, cria a linha do artigo) + `runX()` (chama IA de verdade) desde a
 * Fase 9.1b — o controller chama `prepareX()` inline e despacha um `Job` pra
 * `runX()` rodar no worker (ou inline mesmo, se `QUEUE_DRIVER=sync`).
 */
final class ArticlePipeline
{
    private const PROVIDER = 'gemini';

    private AIProvider $ai;
    private PromptBuilder $prompts;
    /** Tentativas por linhagem antes de BLOCKED (fluxo-editorial §29). */
    private const MAX_ATTEMPTS = 3;

    private ArticleService $articles;
    private AiExecutionService $executions;
    private ArticleNoteService $notes;
    private CategoryService $categories;
    private FeedbackService $feedback;
    private EditorialMemoryService $memory;
    private ImageService $imageStore;
    private ?ImageProvider $imageProvider;
    private RetryRunner $retry;
    private NotificationService $notifications;
    private WordPressConnectionService $wpConnections;

    public function __construct(
        ?AIProvider $ai = null,
        ?PromptBuilder $prompts = null,
        ?ArticleService $articles = null,
        ?AiExecutionService $executions = null,
        ?ArticleNoteService $notes = null,
        ?CategoryService $categories = null,
        ?RetryRunner $retry = null,
        ?ImageService $imageStore = null,
        ?ImageProvider $imageProvider = null,
        ?NotificationService $notifications = null,
        ?WordPressConnectionService $wpConnections = null,
    ) {
        $this->ai = $ai ?? new GeminiProvider();
        $this->prompts = $prompts ?? new PromptBuilder();
        $this->articles = $articles ?? new ArticleService();
        $this->executions = $executions ?? new AiExecutionService();
        $this->notes = $notes ?? new ArticleNoteService();
        $this->categories = $categories ?? new CategoryService();
        $this->feedback = new FeedbackService();
        $this->memory = new EditorialMemoryService();
        $this->imageStore = $imageStore ?? new ImageService();
        $this->notifications = $notifications ?? new NotificationService();
        $this->wpConnections = $wpConnections ?? new WordPressConnectionService();
        // Instanciado sob demanda em generateImages() — não exige IMAGE_API_KEY
        // quando o pipeline roda sem a etapa de imagem (ex.: testes).
        $this->imageProvider = $imageProvider;
        // Padrão = backoff curto (bom pra quando roda inline, driver sync).
        // bin/worker.php passa RetryPolicy::default() (backoff longo) — é ele
        // quem sabe se está rodando em segundo plano (Fase 9.1b).
        $this->retry = $retry ?? new RetryRunner(RetryPolicy::inline());
    }

    /**
     * Só a parte síncrona e rápida (sem chamada a IA): cria a linha do artigo
     * (`PLANNED`) e devolve o `article_id` — pra quem chamou (controller) já
     * ter o que precisa pra redirecionar/despachar o job, antes do trabalho
     * pesado rodar (Fase 9.1b — fila conectada ao pipeline).
     */
    /** @param 'MANUAL'|'AUTO' $source repassado direto pra ArticleService::create() */
    public function prepareGenerate(int $siteId, ?int $goalId = null, string $source = 'MANUAL'): int
    {
        return $this->articles->create($siteId, $goalId, $source);
    }

    /**
     * A parte que chama IA de verdade — roda inline (driver síncrono) ou pelo
     * worker (driver Redis), sem diferença de código. Espera um artigo já
     * criado por {@see self::prepareGenerate()}.
     *
     * @return array{article_id:int, title:string, word_count:int, cost:float, recommendation:string, warnings:list<string>}
     * @throws PipelineException
     */
    public function runGenerate(int $articleId, int $siteId, ?int $goalId = null, ?int $categoryId = null): array
    {
        $this->prompts->setEditorialContext($this->siteMemoryContext($siteId));

        try {
            return $this->run($articleId, $siteId, $goalId, $categoryId);
        } finally {
            $this->prompts->setEditorialContext(null);
        }
    }

    /**
     * Só a parte síncrona e rápida da regeneração (fluxo-editorial §29,
     * RF-010) OU de uma falha técnica definitiva (`ERROR`, Fase 9.1b/9.4 —
     * "tentar de novo", conceito diferente mas mesma mecânica de linhagem):
     * valida o artigo anterior, resolve a linhagem, e — ao passar de
     * {@see self::MAX_ATTEMPTS} tentativas — marca `BLOCKED` e já lança (esse
     * caso nunca chega a enfileirar nada). Sem chamada a IA.
     *
     * `$userId` é quem clicou "Regenerar" agora (AuthService::id() do
     * controller) — é pra ele que a notificação de BLOCKED vai, não pra
     * equipe inteira do site (pedido do responsável, 2026-09-08).
     *
     * @return array{article_id:int, site_id:int, goal_id:?int, category_id:?int, lineage_id:int}
     * @throws PipelineException
     */
    public function prepareRegenerate(int $previousArticleId, ?int $userId = null): array
    {
        $prev = $this->articles->findById($previousArticleId);
        if ($prev === null) {
            throw new PipelineException('Artigo não encontrado.', $previousArticleId, 'regenerate');
        }
        if (!in_array($prev['status'], ['REVISION_REQUESTED', 'ERROR'], true)) {
            throw new PipelineException(
                'Só é possível regenerar um artigo rejeitado ou com falha técnica (status atual: ' . $prev['status'] . ').',
                $previousArticleId,
                'regenerate',
            );
        }

        $lineageId = (int) ($prev['lineage_id'] ?: $prev['id']);
        $attempt = (int) $prev['attempt_number'] + 1;

        if ($attempt > self::MAX_ATTEMPTS) {
            $this->articles->setStatus($previousArticleId, 'BLOCKED');
            $siteId = (int) $prev['site_id'];
            $type = NotificationService::TYPE_ATTENTION;
            $title = 'Artigo bloqueado';
            $message = '"' . ($prev['title'] ?: 'Rascunho #' . $previousArticleId) . '" esgotou as ' . self::MAX_ATTEMPTS . ' tentativas — decisão humana necessária.';
            $link = '/sites/' . $siteId . '/production/' . $previousArticleId;
            if ($userId !== null) {
                $this->notifications->notify($userId, $type, $title, $message, $siteId, $link);
            } else {
                $this->notifications->notifySiteTeam($siteId, $type, $title, $message, $link);
            }
            throw new PipelineException(
                'Limite de ' . self::MAX_ATTEMPTS . ' tentativas atingido nesta linhagem — artigo BLOQUEADO. Decisão humana necessária.',
                $previousArticleId,
                'regenerate',
            );
        }

        $this->articles->setLineage((int) $prev['id'], (int) $prev['id']);

        $siteId = (int) $prev['site_id'];
        $goalId = $prev['goal_id'] !== null ? (int) $prev['goal_id'] : null;
        $categoryId = $prev['category_id'] !== null ? (int) $prev['category_id'] : null;

        $articleId = $this->articles->createAttempt($siteId, $goalId, $lineageId, $attempt);

        return [
            'article_id'  => $articleId,
            'site_id'     => $siteId,
            'goal_id'     => $goalId,
            'category_id' => $categoryId,
            'lineage_id'  => $lineageId,
        ];
    }

    /**
     * A parte que chama IA de verdade — espera um artigo já criado por
     * {@see self::prepareRegenerate()}.
     *
     * @return array{article_id:int, title:string, word_count:int, cost:float, recommendation:string, warnings:list<string>}
     * @throws PipelineException
     */
    public function runRegenerate(int $articleId, int $siteId, ?int $goalId, ?int $categoryId, int $lineageId): array
    {
        $context = array_filter([
            $this->lineageFeedbackContext($lineageId),
            $this->siteMemoryContext($siteId),
        ], static fn (string $s): bool => $s !== '');
        $this->prompts->setEditorialContext(implode("\n\n", $context));

        try {
            return $this->run($articleId, $siteId, $goalId, $categoryId);
        } finally {
            $this->prompts->setEditorialContext(null);
        }
    }

    /**
     * @return array{article_id:int, title:string, word_count:int, cost:float, recommendation:string, warnings:list<string>}
     * @throws PipelineException
     */
    private function run(int $articleId, int $siteId, ?int $goalId, ?int $categoryId): array
    {
        $warnings = [];

        // --- planning ---------------------------------------------------------
        // Mesma lista (posts publicados de verdade no WordPress, qualquer um,
        // antigo ou novo) alimenta duas checagens diferentes: `planning` usa
        // pra evitar canibalização, `writing`/`seo`/`compliance` usam pra
        // linkar internamente (ver setInternalLinkCandidates() abaixo) — uma
        // chamada só à API, reaproveitada nos dois lugares.
        $publishedDigest = $this->existingWordPressPostsDigest($siteId);
        $this->prompts->setExistingContentContext($publishedDigest);
        $plan = $this->step('planning', $articleId, $siteId, $goalId, $categoryId);
        $this->prompts->setExistingContentContext(null); // escopo só do planning — não faz sentido nos passos depois

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
        $researchGaps = array_values(array_map('strval', (array) ($research['gaps'] ?? [])));
        if ($researchGaps !== []) {
            $warnings[] = 'Pesquisa com lacunas: ' . implode('; ', $researchGaps);
        }

        // --- writing --------------------------------------------------------
        $this->prompts->setInternalLinkCandidates($publishedDigest);
        $writeBrief = $brief + ['notes' => $this->researchDigest($research)];
        $writing = $this->step('writing', $articleId, $siteId, $goalId, $resolvedCategoryId, $writeBrief);

        $html = (string) ($writing['content_html'] ?? '');
        if (trim($html) === '') {
            throw new PipelineException('O passo writing não retornou content_html.', $articleId, 'writing');
        }

        // Links internos e fontes externas — confere os dois aqui, na
        // geração, não só na publicação (achado real, 2026-09-09: rascunho em
        // revisão nunca passava por nenhuma dessas checagens — só rodavam
        // depois de publicado —, então quem revisava via link morto/inventado
        // que só ia ser corrigido bem mais tarde, ou nunca, se o rascunho
        // fosse rejeitado antes de publicar). `WordPressPublishService`
        // continua rodando os dois de novo na publicação — rede de segurança
        // pra quando o site cair entre a geração e a publicação de verdade.
        $internalCheck = $this->resolveInternalLinks($siteId, $html);
        $html = $internalCheck['html'];
        if ($internalCheck['unwrapped'] > 0) {
            $warnings[] = $internalCheck['unwrapped'] . ' link(s) interno(s) removido(s) por não bater com nenhum post real do WordPress.';
        }

        $externalCheck = (new ExternalLinkVerifier())->verify($html);
        $html = $externalCheck['html'];
        if ($externalCheck['unwrapped'] > 0) {
            $warnings[] = $externalCheck['unwrapped'] . ' link(s) de fonte removido(s) por estarem fora do ar (404) — texto da citação mantido.';
        }
        $ambiguousLinks = $externalCheck['ambiguous'];
        if ($ambiguousLinks !== []) {
            // Não decide por adivinhação (ver ExternalLinkVerifier) — devolve
            // a decisão pro humano, com a URL exata de cada um. Guardado
            // também de forma estruturada (não só no texto de `warnings`)
            // porque o agendamento passa a exigir confirmação manual de cada
            // um antes de publicar (achado real, 2026-09-10).
            $warnings[] = count($ambiguousLinks) . ' link(s) de fonte não puderam ser confirmados automaticamente '
                . '(bloqueio comum de bot em domínio grande — não significa que estão mortos) — confira à mão: '
                . implode(', ', $ambiguousLinks);
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
        $this->prompts->setInternalLinkCandidates(null); // escopo só de writing/seo/compliance

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

        // --- imagens (Fase 5.3) ------------------------------------------------
        // Nunca bloqueia: falha de imagem vira aviso, o artigo segue em revisão.
        try {
            $this->generateImages($articleId, $siteId, $goalId, $resolvedCategoryId, $brief, $draft, $warnings);
        } catch (Throwable $e) {
            $warnings[] = 'Geração de imagens falhou: ' . $e->getMessage();
        }

        // Achado real (2026-09-09): $warnings era calculado o pipeline todo
        // (link removido, canibalização, pesquisa sem fonte, palavra abaixo
        // do mínimo...) mas NINGUÉM lia esse retorno — nem ArticleJobHandlers
        // nem ProductionController pegam a chave 'warnings' de runGenerate()/
        // runRegenerate(). Gravar como nota persiste de verdade e deixa
        // visível em production/show.php (mesmo padrão dos outros passos).
        if ($warnings !== [] || $ambiguousLinks !== []) {
            $this->notes->save($articleId, 'pipeline', [
                'warnings' => $warnings,
                'ambiguous_links' => $ambiguousLinks,
                // Cópia separada de $researchGaps (achado real 2026-09-15, recomendação
                // do relatório de Inteligência): dentro de `warnings` a lacuna vira só
                // texto solto, misturada com avisos de link/canibalização/SEO — sem
                // isolar, não dá pra agregar "lacunas de pesquisa do site" sem parsear
                // a frase de novo (ver ArticleNoteService::researchGapsForSite()).
                'research_gaps' => $researchGaps,
            ]);
        }

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
     * Brief visual (passo `image`, via Gemini) + geração das opções pelo Nano Banana
     * (fluxo-editorial §25). Grava em `images` (selected=0 — a escolha é do
     * Redator-Chefe, Fase 5.4) e converte para WebP (seo.md) quando `gd` existe.
     *
     * @param array<string,string> $brief
     * @param array<string,mixed>  $draft
     * @param list<string>          $warnings
     */
    private function generateImages(int $articleId, int $siteId, ?int $goalId, ?int $categoryId, array $brief, array $draft, array &$warnings): void
    {
        // 1. brief visual — rastreado em ai_executions/article_ai_notes por step()
        $visual = $this->step('image', $articleId, $siteId, $goalId, $categoryId, $brief, $draft);

        $styleNotes = trim((string) ($visual['style_notes'] ?? ''));
        $specs = [];
        foreach ((array) ($visual['images'] ?? []) as $b) {
            if (!is_array($b)) {
                continue;
            }
            $prompt = trim((string) ($b['prompt'] ?? ''));
            if ($prompt === '') {
                continue;
            }
            // Reforço determinístico (achado real 2026-09-10): docs/ai/image.md
            // já pede "sem texto embutido", mas isso só vale se a IA do passo
            // `image` obedecer — o gerador em si não impõe nada. Mesmo
            // princípio já usado pros links (não confiar só na IA obedecer):
            // todo prompt enviado ao gerador leva este sufixo, sempre.
            $prompt .= ', no text, no letters, no words, no writing, no captions, no signage in the image';
            $ar = (string) ($b['aspect_ratio'] ?? '16:9');
            $specs[] = [
                'role'   => ($b['role'] ?? 'BODY') === 'FEATURED' ? 'FEATURED' : 'BODY',
                'prompt' => $prompt,
                'alt'    => trim((string) ($b['alt_text'] ?? '')),
                'ar'     => in_array($ar, ImageRequest::ASPECT_RATIOS, true) ? $ar : '16:9',
            ];
        }
        if ($specs === []) {
            $warnings[] = 'Brief visual não retornou nenhuma imagem utilizável — artigo sem imagens.';
            return;
        }

        // Imagens de corpo sempre na MESMA proporção da destacada (pedido do
        // Redator-Chefe 2026-09-22) — a IA do passo `image` pode sugerir uma
        // proporção diferente pra cada uma (ex. destacada 16:9, corpo 4:3),
        // mas visualmente elas convivem no mesmo artigo, então precisam
        // combinar. A proporção da própria destacada continua vindo da IA
        // (é ela quem decide o enquadramento principal do artigo).
        $featuredAr = null;
        foreach ($specs as $spec) {
            if ($spec['role'] === 'FEATURED') {
                $featuredAr = $spec['ar'];
                break;
            }
        }
        if ($featuredAr !== null) {
            foreach ($specs as &$spec) {
                if ($spec['role'] === 'BODY') {
                    $spec['ar'] = $featuredAr;
                }
            }
            unset($spec);
        }

        $size = (string) (Env::get('IMAGE_DEFAULT_SIZE', '2K'));
        if (!in_array($size, ImageRequest::SIZES, true)) {
            $size = '2K';
        }
        $featuredOptions = max(1, (int) Env::get('IMAGE_FEATURED_OPTIONS', '3'));

        if (!ImageConverter::available()) {
            $warnings[] = 'Extensão PHP `gd` não habilitada — imagens salvas no formato original (seo.md pede WebP).';
        }

        $provider = $this->imageProvider ?? new NanoBananaProvider();

        $execId = $this->executions->create($articleId, 'image', 'nano-banana');
        $this->executions->markRunning($execId);

        $storage = new ImageStorage();
        $cost = 0.0;
        $made = 0;
        $seq = ['FEATURED' => 0, 'BODY' => 0];

        foreach ($specs as $spec) {
            $copies = $spec['role'] === 'FEATURED' ? $featuredOptions : 1;
            for ($i = 1; $i <= $copies; $i++) {
                $seq[$spec['role']]++;
                try {
                    $request = new ImageRequest($spec['prompt'], $spec['ar'], $size);
                    $result = $this->retry->run(
                        fn () => $provider->generate($request),
                        fn (int $a, AIException $e) => null,
                    );
                } catch (Throwable $e) {
                    $warnings[] = "Imagem {$spec['role']} #{$i} falhou: " . $e->getMessage();
                    continue;
                }

                $cost += ImagePricing::estimate($result->model, $size);

                $bytes = $result->bytes;
                $format = $result->extension();
                if (ImageConverter::available()) {
                    try {
                        $bytes = ImageConverter::toWebp($result->bytes);
                        $format = 'webp';
                    } catch (Throwable $e) {
                        $warnings[] = 'WebP não gerado (' . $e->getMessage() . ') — imagem em ' . $format . '.';
                    }
                }

                try {
                    $url = $storage->save($siteId, $articleId, strtolower($spec['role']) . '-' . $seq[$spec['role']], $bytes, $format);
                } catch (Throwable $e) {
                    $warnings[] = "Falha ao gravar imagem {$spec['role']} #{$i}: " . $e->getMessage();
                    continue;
                }

                $this->imageStore->add(
                    $articleId,
                    $spec['role'],
                    $url,
                    $spec['prompt'] . ($styleNotes !== '' ? "\n\nStyle: " . $styleNotes : ''),
                    $spec['alt'] !== '' ? $spec['alt'] : null,
                    $format,
                    $spec['ar'],
                );
                $made++;
            }
        }

        if ($made === 0) {
            $this->executions->markFailed($execId, 'nenhuma imagem gerada');
            $warnings[] = 'Nenhuma imagem foi gerada.';
            return;
        }

        $this->executions->markSuccessCost($execId, $cost);
    }

    /**
     * Gera mais UMA imagem de corpo pro artigo (ação manual do Redator-Chefe,
     * pedida na tela do artigo — fora do fluxo automático). Roda o passo
     * `image` de novo com o conteúdo ATUAL do artigo (pode ter sido editado
     * desde a geração original) e aproveita só o primeiro item de papel BODY
     * da resposta — o resto é descartado; cada clique gera exatamente 1
     * imagem nova, nunca o lote inteiro de novo. Proporção sempre igual à da
     * imagem destacada em uso (pedido 2026-09-22 — ver
     * `ImageService::featuredAspectRatio()`).
     *
     * @return array{id:int, url:string}
     * @throws PipelineException
     */
    public function addBodyImage(int $articleId, int $siteId, ?int $goalId, ?int $categoryId): array
    {
        $article = $this->articles->findById($articleId);
        if ($article === null) {
            throw new PipelineException('Artigo não encontrado.', $articleId, 'image');
        }
        $version = $this->articles->latestVersion($articleId);

        $brief = [
            'title'         => (string) ($article['title'] ?? ''),
            'focus_keyword' => (string) ($article['focus_keyword'] ?? ''),
        ];
        $draft = [
            'title'             => (string) ($article['title'] ?? ''),
            'meta_description'  => (string) ($article['meta_description'] ?? ''),
            'content_html'      => (string) ($version['content'] ?? ''),
            'word_count'        => (int) ($version['word_count'] ?? 0),
        ];

        $visual = $this->step('image', $articleId, $siteId, $goalId, $categoryId, $brief, $draft);
        $styleNotes = trim((string) ($visual['style_notes'] ?? ''));

        $spec = null;
        foreach ((array) ($visual['images'] ?? []) as $b) {
            if (is_array($b) && ($b['role'] ?? 'BODY') === 'BODY' && trim((string) ($b['prompt'] ?? '')) !== '') {
                $spec = $b;
                break;
            }
        }
        if ($spec === null) {
            throw new PipelineException('A IA não sugeriu nenhuma imagem de corpo nova pra este artigo.', $articleId, 'image');
        }

        $prompt = trim((string) $spec['prompt'])
            . ', no text, no letters, no words, no writing, no captions, no signage in the image';
        $prompt .= $styleNotes !== '' ? "\n\nStyle: " . $styleNotes : '';
        $alt = trim((string) ($spec['alt_text'] ?? ''));

        return $this->generateAndStoreOneImage($articleId, $siteId, 'BODY', $prompt, $alt !== '' ? $alt : null);
    }

    /**
     * Regenera (substitui) uma imagem já existente — reenvia o MESMO prompt
     * que gerou a original pro gerador (o resultado varia a cada chamada,
     * não é determinístico) e troca uma pela outra. Mantém papel, alt text e
     * se estava selecionada. Pedido 2026-09-22 (botão "Substituir" em cada
     * imagem de corpo).
     *
     * @return array{id:int, url:string}
     * @throws PipelineException
     */
    public function regenerateImage(int $articleId, int $siteId, int $imageId): array
    {
        $old = $this->imageStore->find($articleId, $imageId);
        if ($old === null) {
            throw new PipelineException('Imagem não encontrada.', $articleId, 'image');
        }
        $prompt = trim((string) ($old['prompt'] ?? ''));
        if ($prompt === '') {
            throw new PipelineException('Esta imagem não tem prompt salvo — não dá pra regenerar automaticamente.', $articleId, 'image');
        }

        $role = (string) $old['role'] === 'FEATURED' ? 'FEATURED' : 'BODY';
        $alt = $old['alt_text'] !== null ? (string) $old['alt_text'] : null;
        $wasSelected = (bool) $old['selected'];

        // O prompt gravado (ImageService::add()) já é o prompt final enviado
        // ao gerador da vez passada — inclui o sufixo "no text..." e o bloco
        // "Style: ..." quando houve. Reenviar como está reproduz a mesma
        // intenção visual, só com um resultado novo do modelo.
        $new = $this->generateAndStoreOneImage($articleId, $siteId, $role, $prompt, $alt);

        $this->imageStore->delete($articleId, $imageId);
        (new ImageStorage())->delete((string) $old['url']);
        if ($wasSelected) {
            $this->imageStore->select($articleId, $new['id']);
        }

        return $new;
    }

    /**
     * Núcleo comum das ações manuais acima: chama o provedor de imagem pra
     * UM pedido, converte pra WebP quando possível e grava em `images`.
     * Proporção: sempre a da imagem destacada em uso pro artigo (`null` ==
     * artigo ainda sem destacada, ou linha antiga sem essa informação —
     * migration 0025 —, cai no padrão 16:9 do próprio `ImageRequest`).
     *
     * @param 'FEATURED'|'BODY' $role
     * @return array{id:int, url:string}
     * @throws PipelineException
     */
    private function generateAndStoreOneImage(int $articleId, int $siteId, string $role, string $prompt, ?string $alt): array
    {
        $size = (string) (Env::get('IMAGE_DEFAULT_SIZE', '2K'));
        if (!in_array($size, ImageRequest::SIZES, true)) {
            $size = '2K';
        }
        $ar = $this->imageStore->featuredAspectRatio($articleId) ?? '16:9';

        $provider = $this->imageProvider ?? new NanoBananaProvider();
        $execId = $this->executions->create($articleId, 'image', 'nano-banana');
        $this->executions->markRunning($execId);

        try {
            $request = new ImageRequest($prompt, $ar, $size);
            $result = $this->retry->run(
                fn () => $provider->generate($request),
                fn (int $a, AIException $e) => null,
            );
        } catch (Throwable $e) {
            $this->executions->markFailed($execId, $e->getMessage());
            throw new PipelineException('Geração de imagem falhou: ' . $e->getMessage(), $articleId, 'image', $e);
        }

        $cost = ImagePricing::estimate($result->model, $size);
        $bytes = $result->bytes;
        $format = $result->extension();
        if (ImageConverter::available()) {
            try {
                $bytes = ImageConverter::toWebp($result->bytes);
                $format = 'webp';
            } catch (Throwable) {
                // mantém no formato original — mesmo comportamento de generateImages().
            }
        }

        try {
            $storage = new ImageStorage();
            $url = $storage->save($siteId, $articleId, strtolower($role) . '-' . uniqid(), $bytes, $format);
        } catch (Throwable $e) {
            $this->executions->markFailed($execId, $e->getMessage());
            throw new PipelineException('Falha ao gravar a imagem: ' . $e->getMessage(), $articleId, 'image', $e);
        }

        $id = $this->imageStore->add($articleId, $role, $url, $prompt, $alt, $format, $ar);
        $this->executions->markSuccessCost($execId, $cost);

        return ['id' => $id, 'url' => $url];
    }

    /**
     * Resolve os links internos que a IA escreveu contra o WordPress de
     * verdade (mesma lógica do `InternalLinkResolver`, já usado na
     * publicação) — aqui rodando na geração, pra quem revisa o rascunho já
     * ver o link certo ou nenhum, nunca um `/blog/slug-inventado`. Sem
     * WordPress conectado (ou falha de rede), devolve o HTML sem mexer —
     * o `InternalLinkResolver` na publicação continua sendo a rede de
     * segurança final de qualquer forma.
     *
     * @return array{html:string, rewritten:int, unwrapped:int}
     */
    private function resolveInternalLinks(int $siteId, string $html): array
    {
        try {
            $client = $this->wpConnections->client($siteId);
            $baseUrl = (string) ($this->wpConnections->forSite($siteId)['url'] ?? '');
        } catch (WordPressException) {
            return ['html' => $html, 'rewritten' => 0, 'unwrapped' => 0];
        }

        return (new InternalLinkResolver($client, $baseUrl))->resolve($html);
    }

    /**
     * Lista (texto) dos posts publicados no WordPress do site — direto da
     * API, não da tabela local `articles`. Serve dois propósitos:
     * `planning` usa pra checar duplicação contra o site de verdade
     * (`PromptBuilder::existingContentLayer()`); `writing`/`seo`/`compliance`
     * usam a MESMA lista pra saber quais artigos são alvo válido de link
     * interno (`PromptBuilder::setInternalLinkCandidates()`, achado real
     * 2026-09-14 — antes disso, o alvo de link interno vinha só da tabela
     * local `articles`, que só rastreia o que foi gerado por este app e pode
     * sair de sincronia com o WordPress de verdade; qualquer post publicado
     * antes deste app existir, ou por fora dele, nunca era considerado).
     * Null quando o site ainda não tem WordPress conectado, ou a chamada
     * falha (rede, credencial) — nunca deve travar a geração por isso, é
     * só um insumo extra, o pipeline já funciona sem ele.
     */
    private function existingWordPressPostsDigest(int $siteId): ?string
    {
        try {
            $posts = $this->wpConnections->client($siteId)->listRecentPosts();
        } catch (WordPressException) {
            return null;
        }

        if ($posts === []) {
            return null;
        }

        $lines = array_map(
            static fn (array $p): string => '- ' . $p['title'] . ' (' . $p['link'] . ')',
            $posts,
        );

        return implode("\n", $lines);
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

    /**
     * Monta o texto de "memória editorial" a partir do feedback de todas as
     * tentativas anteriores da linhagem — para a IA corrigir na regeneração.
     */
    private function lineageFeedbackContext(int $lineageId): string
    {
        $items = $this->feedback->forLineage($lineageId);
        if ($items === []) {
            return '';
        }

        $lines = ['Este conteúdo já foi rejeitado pelo Redator-Chefe. Corrija TODOS os pontos abaixo nesta nova versão:'];
        foreach ($items as $f) {
            $reason = ArticleReviewService::REJECT_REASONS[$f['reason']] ?? (string) $f['reason'];
            $lines[] = '- [tentativa ' . (int) $f['attempt_number'] . ' · ' . $reason . '] ' . trim((string) $f['justification']);
        }

        return implode("\n", $lines);
    }

    /**
     * "Memória editorial" do site: rejeições recentes (fatia 6.3) + artigos já
     * aprovados + lições curadas (Fase 9, `EditorialMemoryService`) — essas
     * últimas escritas sempre por um humano, nunca pela IA (Regra de
     * não-invenção, §58), e duradouras (não somem quando saem da janela das
     * últimas 8 rejeições). Entra em toda geração para a IA aprender com o
     * histórico do site.
     */
    private function siteMemoryContext(int $siteId): string
    {
        $rejections = $this->feedback->recentForSite($siteId, 8);
        $approved = $this->articles->recentApprovedForSite($siteId, 10);
        $curated = $this->memory->activeForSite($siteId);
        if ($rejections === [] && $approved === [] && $curated === []) {
            return '';
        }

        $lines = ['Aprendizado do histórico deste site — leve em conta antes de decidir tema, ângulo e tom:'];

        if ($curated !== []) {
            $lines[] = '';
            $lines[] = 'Lições fixas deste site (curadas pelo Redator-Chefe/Admin):';
            foreach ($curated as $c) {
                $lines[] = '- ' . trim((string) $c['lesson']);
            }
        }

        if ($rejections !== []) {
            $lines[] = '';
            $lines[] = 'Rejeições recentes do Redator-Chefe (não repita esses erros):';
            foreach ($rejections as $r) {
                $reason = ArticleReviewService::REJECT_REASONS[$r['reason']] ?? (string) $r['reason'];
                $lines[] = '- [' . $reason . '] ' . trim((string) $r['justification']);
            }
        }

        if ($approved !== []) {
            $lines[] = '';
            $lines[] = 'Temas/palavras-chave já aprovados neste site (não repita nem canibalize):';
            foreach ($approved as $art) {
                $kw = trim((string) ($art['focus_keyword'] ?? ''));
                $lines[] = '- ' . trim((string) ($art['title'] ?? '(sem título)')) . ($kw !== '' ? " — {$kw}" : '');
            }
        }

        return implode("\n", $lines);
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

    /**
     * Achado real (2026-09-10): este resumo incluía o NOME da fonte mas nunca
     * a `source_url` — a pesquisa achava links reais, mas o passo `writing`
     * nunca recebia a URL em si, só o rótulo ("fonte: Buffer"). Com o
     * protocolo anti-alucinação (docs/ai/research.md) funcionando, a IA
     * corretamente se recusava a citar sem link real disponível — resultado
     * prático: artigo saía com ZERO links externos, mesmo a pesquisa tendo
     * fontes de verdade. Não é o prompt que estava errado, era este dado
     * que nunca chegava.
     *
     * @param array<string, mixed> $research
     */
    private function researchDigest(array $research): string
    {
        $lines = [];
        foreach ((array) ($research['findings'] ?? []) as $f) {
            if (!is_array($f)) {
                continue;
            }
            $claim = trim((string) ($f['claim'] ?? ''));
            $src = trim((string) ($f['source_title'] ?? $f['publisher'] ?? ''));
            $url = trim((string) ($f['source_url'] ?? ''));
            if ($claim === '') {
                continue;
            }
            $tag = array_values(array_filter([$src, $url]));
            $lines[] = '- ' . $claim . ($tag !== [] ? ' (fonte: ' . implode(' — ', $tag) . ')' : '');
        }

        return $lines === [] ? '(pesquisa sem fatos utilizáveis)' : "Fatos da pesquisa:\n" . implode("\n", $lines);
    }
}
