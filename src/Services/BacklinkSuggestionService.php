<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\AIProvider;
use App\Integrations\Gemini\GeminiProvider;
use App\Services\Pipeline\StepSchemas;
use App\Support\HtmlLinks;
use Throwable;

/**
 * Link interno retroativo (Central de Links, achado real 2026-09-10): o
 * passo `writing` já sabe linkar um artigo NOVO pros artigos antigos
 * (`PromptBuilder::internalLinksLayer()`), mas o caminho inverso nunca
 * existia — artigo antigo nunca ganhava um link de volta pro artigo novo
 * que acabou de publicar. Isso mantinha a rede de links interna sempre
 * "rasa". Roda como varredura periódica (`bin/worker.php`), não como parte
 * do pipeline de geração — por isso chama `GeminiProvider` direto, sem
 * `PromptBuilder`/`ArticlePipeline` (mesmo padrão de `IntelligenceService`,
 * outro caso de chamada de IA avulsa fora do pipeline de artigo).
 *
 * Nunca aplica nada sozinho: só grava sugestões (nota `pipeline` do artigo
 * ANTIGO) pro Redator-Chefe aprovar na Central de Links
 * (`LinkFixService::applyBacklink()`).
 */
final class BacklinkSuggestionService
{
    private const PROVIDER = 'gemini';
    private const CANDIDATE_LIMIT = 15;

    private AIProvider $ai;
    private ArticleService $articles;
    private ArticleNoteService $notes;
    private AiExecutionService $executions;
    private SiteService $sites;
    private ScheduleService $schedules;

    public function __construct(
        ?AIProvider $ai = null,
        ?ArticleService $articles = null,
        ?ArticleNoteService $notes = null,
        ?AiExecutionService $executions = null,
        ?SiteService $sites = null,
        ?ScheduleService $schedules = null,
    ) {
        $this->ai = $ai ?? new GeminiProvider();
        $this->articles = $articles ?? new ArticleService();
        $this->notes = $notes ?? new ArticleNoteService();
        $this->executions = $executions ?? new AiExecutionService();
        $this->sites = $sites ?? new SiteService();
        $this->schedules = $schedules ?? new ScheduleService();
    }

    public function scan(int $newArticleId, int $siteId): void
    {
        $newArticle = $this->articles->find($siteId, $newArticleId);
        $site = $this->sites->find($siteId);
        $newSchedule = $this->schedules->latestForArticle($newArticleId);

        if ($newArticle === null || $site === null || $newSchedule === null || empty($newSchedule['wordpress_post_id'])) {
            $this->markScanned($newArticleId); // nada pra fazer (ainda sem post real) — não tenta de novo
            return;
        }

        $candidates = $this->articles->publishedCandidatesForBacklinks($siteId, $newArticleId, self::CANDIDATE_LIMIT);
        if ($candidates === []) {
            $this->markScanned($newArticleId);
            return;
        }

        $newUrl = rtrim((string) ($site['wordpress_url'] ?? ''), '/') . '/?p=' . (int) $newSchedule['wordpress_post_id'];

        $execId = $this->executions->create($newArticleId, 'backlink_suggestions', self::PROVIDER);
        $this->executions->markRunning($execId);

        try {
            $result = $this->ai->generateJson(
                $this->prompt($newArticle, $newUrl, $candidates),
                StepSchemas::backlinkSuggestions(),
            );
        } catch (Throwable $e) {
            $this->executions->markFailed($execId, $e->getMessage());
            $this->markScanned($newArticleId);
            return;
        }

        if ($result->json === null) {
            $this->executions->markFailed($execId, 'resposta da IA não era JSON válido');
            $this->markScanned($newArticleId);
            return;
        }
        $this->executions->markSuccess($execId, $result);

        $byId = [];
        foreach ($candidates as $c) {
            $byId[$c['id']] = $c;
        }

        foreach ((array) ($result->json['suggestions'] ?? []) as $s) {
            if (!is_array($s)) {
                continue;
            }
            $targetArticleId = (int) ($s['article_id'] ?? 0);
            $anchorText = trim((string) ($s['anchor_text'] ?? ''));
            if ($anchorText === '' || !isset($byId[$targetArticleId])) {
                continue;
            }

            // Nunca confia sem checar: o texto tem que existir de verdade no
            // artigo antigo (mesmo princípio anti-invenção já usado pros
            // links e agora reaplicado pra âncora de link interno).
            $check = HtmlLinks::wrapFirstOccurrence($byId[$targetArticleId]['content'], $anchorText, $newUrl);
            if (!$check['changed']) {
                continue;
            }

            $note = $this->notes->forArticle($targetArticleId)['pipeline'] ?? [];
            $suggestions = (array) ($note['backlink_suggestions'] ?? []);
            $suggestions[] = [
                'from_article_id' => $newArticleId,
                'from_title'      => (string) ($newArticle['title'] ?? ''),
                'anchor_text'     => $anchorText,
                'url'             => $newUrl,
            ];
            $note['backlink_suggestions'] = $suggestions;
            $this->notes->save($targetArticleId, 'pipeline', $note);
        }

        $this->markScanned($newArticleId);
    }

    private function markScanned(int $articleId): void
    {
        $note = $this->notes->forArticle($articleId)['pipeline'] ?? [];
        $note['backlink_scan_done'] = true;
        $this->notes->save($articleId, 'pipeline', $note);
    }

    /**
     * @param array<string, mixed>                                                                            $newArticle
     * @param list<array{id:int, title:string, meta_description:string, focus_keyword:string, content:string}> $candidates
     */
    private function prompt(array $newArticle, string $newUrl, array $candidates): string
    {
        $lines = [];
        $lines[] = 'Você decide quais artigos ANTIGOS de um blog merecem ganhar um link interno de volta pra um artigo';
        $lines[] = 'NOVO que acabou de ser publicado — pra deixar a rede de links interna do site mais rica com o tempo.';
        $lines[] = '';
        $lines[] = '# ARTIGO NOVO (alvo do link)';
        $lines[] = '- Título: ' . (string) ($newArticle['title'] ?? '');
        $lines[] = '- Palavra-chave: ' . (string) ($newArticle['focus_keyword'] ?? '');
        $lines[] = '- Meta descrição: ' . (string) ($newArticle['meta_description'] ?? '');
        $lines[] = '- URL: ' . $newUrl;
        $lines[] = '';
        $lines[] = '# ARTIGOS ANTIGOS CANDIDATOS (corpo completo em HTML)';
        foreach ($candidates as $c) {
            $lines[] = '';
            $lines[] = '## Artigo #' . $c['id'] . ' — ' . $c['title'];
            $lines[] = 'Palavra-chave: ' . $c['focus_keyword'];
            $lines[] = 'Corpo: ' . strip_tags($c['content']);
        }
        $lines[] = '';
        $lines[] = '# REGRAS';
        $lines[] = '1. Só sugira um artigo antigo quando o link for genuinamente relevante pro leitor — nunca force.';
        $lines[] = '2. `anchor_text` tem que ser uma frase ou trecho **copiado literalmente** do corpo do artigo antigo';
        $lines[] = '   mostrado acima — nunca invente, parafraseie ou monte um texto que não existe de verdade ali.';
        $lines[] = '   Se não achar um trecho natural pra ancorar, não sugira esse artigo.';
        $lines[] = '3. Não repita o mesmo artigo antigo mais de uma vez.';
        $lines[] = '4. Sem candidato bom o bastante: devolva `suggestions: []` — é melhor não sugerir nada do que';
        $lines[] = '   forçar um link artificial.';
        $lines[] = '';
        $lines[] = 'Devolva no formato JSON pedido: suggestions (article_id, anchor_text, reason).';

        return implode("\n", $lines);
    }
}
