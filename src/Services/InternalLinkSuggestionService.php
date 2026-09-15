<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\AIProvider;
use App\Integrations\Gemini\GeminiProvider;
use App\Services\Pipeline\StepSchemas;
use RuntimeException;
use Throwable;

/**
 * "Sugerir por relevância" (editor de corpo, achado real 2026-09-10): o
 * painel de links internos só mostrava os artigos publicados mais recentes,
 * sem olhar se tinham a ver com o conteúdo do artigo sendo editado — o
 * redator tinha que julgar título por título. Roda sob demanda (clique,
 * custo real de IA por clique — mesmo padrão de `ExternalLinkSuggestionService`),
 * lendo o pool real de artigos publicados do site
 * (`ArticleService::publishedCandidatesForBacklinks()`) e pedindo pra IA
 * escolher só os que um leitor deste artigo genuinamente se beneficiaria de
 * encontrar linkado — nunca força uma sugestão fraca.
 *
 * Diferente da Central de Links (que insere um link dentro do HTML de um
 * artigo ANTIGO), aqui a sugestão nunca edita conteúdo nenhum: só ordena e
 * filtra a lista de "artigo antigo pra linkar" que o próprio redator já
 * copia e cola manualmente — por isso não precisa confirmar nenhuma frase
 * âncora, só filtrar por id real dentro do pool consultado.
 */
final class InternalLinkSuggestionService
{
    private const PROVIDER = 'gemini';
    private const CANDIDATE_LIMIT = 25;

    private AIProvider $ai;
    private ArticleService $articles;
    private ArticleNoteService $notes;
    private AiExecutionService $executions;

    public function __construct(
        ?AIProvider $ai = null,
        ?ArticleService $articles = null,
        ?ArticleNoteService $notes = null,
        ?AiExecutionService $executions = null,
    ) {
        $this->ai = $ai ?? new GeminiProvider();
        $this->articles = $articles ?? new ArticleService();
        $this->notes = $notes ?? new ArticleNoteService();
        $this->executions = $executions ?? new AiExecutionService();
    }

    /** @return list<array{id:int, title:string, wordpress_post_id:int, reason:string}> */
    public function suggest(int $articleId, int $siteId): array
    {
        $article = $this->articles->find($siteId, $articleId);
        if ($article === null) {
            throw new RuntimeException('Artigo não encontrado.');
        }

        $candidates = $this->articles->publishedCandidatesForBacklinks($siteId, $articleId, self::CANDIDATE_LIMIT);
        if ($candidates === []) {
            $this->saveSuggestions($articleId, []);
            return [];
        }

        $execId = $this->executions->create($articleId, 'internal_link_suggestions', self::PROVIDER);
        $this->executions->markRunning($execId);

        try {
            $result = $this->ai->generateJson($this->prompt($article, $candidates), StepSchemas::internalLinkSuggestions());
        } catch (Throwable $e) {
            $this->executions->markFailed($execId, $e->getMessage());
            throw new RuntimeException('Falha ao gerar sugestões: ' . $e->getMessage());
        }

        if ($result->json === null) {
            $this->executions->markFailed($execId, 'resposta da IA não era JSON válido');
            throw new RuntimeException('A IA não devolveu um formato válido.');
        }
        $this->executions->markSuccess($execId, $result);

        $byId = [];
        foreach ($candidates as $c) {
            $byId[$c['id']] = $c;
        }

        $suggestions = [];
        foreach ((array) ($result->json['suggestions'] ?? []) as $s) {
            if (!is_array($s)) {
                continue;
            }
            $targetId = (int) ($s['article_id'] ?? 0);
            // Nunca confia sem checar: só aceita um id que realmente estava no
            // pool consultado — a IA nunca inventa um artigo que não existe.
            if (!isset($byId[$targetId])) {
                continue;
            }
            $suggestions[] = [
                'id' => $targetId,
                'title' => $byId[$targetId]['title'],
                'wordpress_post_id' => $byId[$targetId]['wordpress_post_id'],
                'reason' => trim((string) ($s['reason'] ?? '')),
            ];
        }

        $this->saveSuggestions($articleId, $suggestions);

        return $suggestions;
    }

    /** @param list<array{id:int, title:string, wordpress_post_id:int, reason:string}> $suggestions */
    private function saveSuggestions(int $articleId, array $suggestions): void
    {
        $note = $this->notes->forArticle($articleId)['pipeline'] ?? [];
        $note['internal_link_suggestions'] = $suggestions;
        $this->notes->save($articleId, 'pipeline', $note);
    }

    /** @param array<string, mixed> $article @param list<array{id:int, title:string, meta_description:string, focus_keyword:string, content:string, wordpress_post_id:int}> $candidates */
    private function prompt(array $article, array $candidates): string
    {
        $lines = [];
        $lines[] = 'Você escolhe quais artigos ANTIGOS já publicados neste site merecem ganhar um link a partir do';
        $lines[] = 'artigo abaixo, sendo editado agora — pra um redator humano copiar e colar o link pronto onde';
        $lines[] = 'achar melhor no texto. Só escolha um artigo antigo quando o link for genuinamente útil pro';
        $lines[] = 'leitor (aprofunda, complementa ou dá contexto pro que está sendo dito) — nunca force uma';
        $lines[] = 'sugestão fraca só pra preencher.';
        $lines[] = '';
        $lines[] = '# ARTIGO SENDO EDITADO';
        $lines[] = '- Título: ' . (string) ($article['title'] ?? '');
        $lines[] = '- Palavra-chave: ' . (string) ($article['focus_keyword'] ?? '');
        $lines[] = '- Meta descrição: ' . (string) ($article['meta_description'] ?? '');
        $lines[] = '';
        $lines[] = '# ARTIGOS ANTIGOS CANDIDATOS (já publicados neste site)';
        foreach ($candidates as $c) {
            $lines[] = '';
            $lines[] = '## Artigo #' . $c['id'] . ' — ' . $c['title'];
            $lines[] = 'Palavra-chave: ' . $c['focus_keyword'];
            $lines[] = 'Meta descrição: ' . $c['meta_description'];
        }
        $lines[] = '';
        $lines[] = '# REGRAS';
        $lines[] = '1. Só use `article_id` de um dos artigos candidatos listados acima — nunca invente um id.';
        $lines[] = '2. `reason` é uma frase curta (1 linha) explicando pro redator por que esse link complementa';
        $lines[] = '   o conteúdo — vai aparecer no painel pra ele decidir se usa.';
        $lines[] = '3. Ordene do mais relevante pro menos relevante.';
        $lines[] = '4. Sem candidato realmente relevante: devolva `suggestions: []` — é melhor não sugerir nada do';
        $lines[] = '   que forçar um link artificial.';
        $lines[] = '';
        $lines[] = 'Devolva no formato JSON pedido: suggestions (article_id, reason).';

        return implode("\n", $lines);
    }
}
