<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\AIProvider;
use App\Integrations\Gemini\GeminiProvider;
use App\Services\Pipeline\StepSchemas;
use RuntimeException;
use Throwable;

/**
 * "Sugerir buscas" (página Fontes, pedido 2026-09-22): cada lacuna de
 * pesquisa é uma frase que a IA já escreveu explicando o que NÃO conseguiu
 * confirmar — útil, mas não diz por onde procurar. Este serviço roda sob
 * demanda (o redator clica, não é automático — custo real de IA por clique,
 * mesmo princípio de `ExternalLinkSuggestionService`/`InternalLinkSuggestionService`)
 * e devolve buscas prontas + palavras-chave + tipo de fonte esperado, pra
 * economizar o trabalho de transformar a lacuna num plano de busca.
 *
 * Nunca sugere uma URL — isso é papel do passo `research`/`ExternalLinkSuggestionService`,
 * que verificam de verdade antes de mostrar; aqui é só "o que pesquisar",
 * sempre checado por um humano depois.
 */
final class ResearchGapHintService
{
    private const PROVIDER = 'gemini';

    private AIProvider $ai;
    private ArticleNoteService $notes;
    private AiExecutionService $executions;
    private SiteService $sites;

    public function __construct(
        ?AIProvider $ai = null,
        ?ArticleNoteService $notes = null,
        ?AiExecutionService $executions = null,
        ?SiteService $sites = null,
    ) {
        $this->ai = $ai ?? new GeminiProvider();
        $this->notes = $notes ?? new ArticleNoteService();
        $this->executions = $executions ?? new AiExecutionService();
        $this->sites = $sites ?? new SiteService();
    }

    /**
     * @return array{queries:list<string>, keywords:list<string>, source_types:list<string>}
     * @throws RuntimeException
     */
    public function suggest(int $articleId, int $siteId, int $gapIndex, string $gapText): array
    {
        if (trim($gapText) === '') {
            throw new RuntimeException('Lacuna vazia.');
        }

        $site = $this->sites->find($siteId);

        $execId = $this->executions->create($articleId, 'research_gap_hints', self::PROVIDER);
        $this->executions->markRunning($execId);

        try {
            $result = $this->ai->generateJson($this->prompt($site, $gapText), StepSchemas::researchGapHints());
        } catch (Throwable $e) {
            $this->executions->markFailed($execId, $e->getMessage());
            throw new RuntimeException('Falha ao gerar dicas: ' . $e->getMessage());
        }

        if ($result->json === null) {
            $this->executions->markFailed($execId, 'resposta da IA não era JSON válido');
            throw new RuntimeException('A IA não devolveu um formato válido.');
        }
        $this->executions->markSuccess($execId, $result);

        $hint = [
            'queries'      => array_values(array_filter(array_map('trim', array_map('strval', (array) ($result->json['queries'] ?? []))))),
            'keywords'     => array_values(array_filter(array_map('trim', array_map('strval', (array) ($result->json['keywords'] ?? []))))),
            'source_types' => array_values(array_filter(array_map('trim', array_map('strval', (array) ($result->json['source_types'] ?? []))))),
        ];

        $this->notes->saveResearchGapHint($articleId, $gapIndex, $hint);

        return $hint;
    }

    /** @param array<string, mixed>|null $site */
    private function prompt(?array $site, string $gapText): string
    {
        $lines = [];
        $lines[] = 'Um redator humano vai pesquisar manualmente (Google/buscador de sua escolha) pra tentar achar';
        $lines[] = 'uma fonte confiável que confirme algo que a pesquisa automática de um artigo NÃO conseguiu';
        $lines[] = 'confirmar. Você não busca nada de verdade — só ajuda a planejar a busca: nunca invente uma URL';
        $lines[] = 'nem afirme que uma fonte específica existe, só sugira POR ONDE procurar.';
        $lines[] = '';
        $lines[] = '# LACUNA (o que não foi confirmado)';
        $lines[] = $gapText;
        if ($site !== null) {
            $lines[] = '';
            $lines[] = '# CONTEXTO DO SITE';
            $lines[] = '- Nicho: ' . (string) ($site['niche'] ?? '—');
            $lines[] = '- Idioma de publicação: ' . (string) ($site['language'] ?? '—');
        }
        $lines[] = '';
        $lines[] = '# O QUE DEVOLVER';
        $lines[] = '- `queries`: 2 a 4 buscas já prontas pra colar num buscador (use aspas pra frase exata e';
        $lines[] = '  operadores como site:/filetype: quando fizer sentido pro tipo de dado — ex. estatística';
        $lines[] = '  oficial costuma valer a pena um site:gov ou site:oecd.org).';
        $lines[] = '- `keywords`: 3 a 8 termos ou expressões-chave soltos (inclua sinônimos e termos técnicos '
            . 'relacionados), pra variar a busca além das queries prontas.';
        $lines[] = '- `source_types`: 2 a 4 tipos de fonte que provavelmente têm esse dado (ex.: "órgão '
            . 'estatístico oficial", "estudo acadêmico revisado por pares", "relatório de associação do setor").';
        $lines[] = '';
        $lines[] = 'Responda no idioma do redator (português), mesmo se o artigo for publicado em outro idioma —';
        $lines[] = 'isto é uma ajuda de trabalho interna, não conteúdo do site.';

        return implode("\n", $lines);
    }
}
