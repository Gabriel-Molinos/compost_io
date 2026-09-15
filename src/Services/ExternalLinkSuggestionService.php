<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\AIProvider;
use App\Integrations\Gemini\GeminiProvider;
use App\Integrations\WordPress\ExternalLinkVerifier;
use App\Services\Pipeline\StepSchemas;
use RuntimeException;
use Throwable;

/**
 * "Gerar mais links externos relacionados" (editor de corpo, achado real
 * 2026-09-10): o painel de fontes externas só mostrava o que a pesquisa
 * original do artigo já tinha achado — pouco quando o redator quer mais
 * opções pra citar enquanto edita. Roda sob demanda (o redator clica, não é
 * automático — custo real de IA por clique), reaproveitando o mesmo par de
 * princípios já estabelecido nesta sessão pra qualquer link novo: (1) mesma
 * regra anti-invenção do passo de pesquisa (docs/ai/research.md — nunca
 * chutar URL), e (2) nunca confia sem checar de verdade
 * (`ExternalLinkVerifier`) — só devolve URL **confirmada viva**; ambíguo ou
 * morto nunca chega a aparecer pro redator (pedido explícito: "nunca um
 * chute").
 */
final class ExternalLinkSuggestionService
{
    private const PROVIDER = 'gemini';
    private const REQUEST_COUNT = 6;

    private AIProvider $ai;
    private ArticleService $articles;
    private AiExecutionService $executions;

    public function __construct(?AIProvider $ai = null, ?ArticleService $articles = null, ?AiExecutionService $executions = null)
    {
        $this->ai = $ai ?? new GeminiProvider();
        $this->articles = $articles ?? new ArticleService();
        $this->executions = $executions ?? new AiExecutionService();
    }

    /** @return list<array{url:string, title:string, publisher:string}> só as que confirmaram vivas — já salvas em article_sources */
    public function suggest(int $articleId, int $siteId): array
    {
        $article = $this->articles->find($siteId, $articleId);
        if ($article === null) {
            throw new RuntimeException('Artigo não encontrado.');
        }

        $existing = $this->articles->sources($articleId);
        $existingUrls = array_map(static fn (array $s): string => (string) $s['url'], $existing);

        $execId = $this->executions->create($articleId, 'external_link_suggestions', self::PROVIDER);
        $this->executions->markRunning($execId);

        try {
            $result = $this->ai->generateJson($this->prompt($article, $existingUrls), StepSchemas::externalLinkSuggestions());
        } catch (Throwable $e) {
            $this->executions->markFailed($execId, $e->getMessage());
            throw new RuntimeException('Falha ao gerar sugestões: ' . $e->getMessage());
        }

        if ($result->json === null) {
            $this->executions->markFailed($execId, 'resposta da IA não era JSON válido');
            throw new RuntimeException('A IA não devolveu um formato válido.');
        }
        $this->executions->markSuccess($execId, $result);

        $verifier = new ExternalLinkVerifier();
        $added = [];
        foreach ((array) ($result->json['suggestions'] ?? []) as $s) {
            if (!is_array($s)) {
                continue;
            }
            $url = trim((string) ($s['url'] ?? ''));
            if ($url === '' || in_array($url, $existingUrls, true)) {
                continue; // sem URL, ou já está na lista — não duplica
            }

            // Nunca confia sem checar: só aceita o que o verificador confirma
            // vivo de verdade (2xx/3xx). Ambíguo (bloqueio de bot) ou morto
            // nunca chega a aparecer aqui — pedido explícito do responsável.
            $check = $verifier->verify('<a href="' . htmlspecialchars($url, ENT_QUOTES) . '">x</a>');
            if ($check['checked'] === 0 || $check['unwrapped'] > 0 || $check['ambiguous'] !== []) {
                continue;
            }

            $title = trim((string) ($s['title'] ?? '')) ?: $url;
            $publisher = trim((string) ($s['publisher'] ?? ''));

            $this->articles->addSource($articleId, [
                'source_url'   => $url,
                'source_title' => $title,
                'publisher'    => $publisher,
                'accessed_at'  => date('Y-m-d'),
            ]);

            $added[] = ['url' => $url, 'title' => $title, 'publisher' => $publisher];
            $existingUrls[] = $url;
        }

        return $added;
    }

    /** @param array<string, mixed> $article @param list<string> $existingUrls */
    private function prompt(array $article, array $existingUrls): string
    {
        $lines = [];
        $lines[] = 'Você sugere fontes externas REAIS e relevantes pra um artigo já escrito — pra um redator';
        $lines[] = 'humano escolher e citar manualmente enquanto edita. Não é ficção: cada URL será verificada por';
        $lines[] = 'requisição HTTP de verdade antes de qualquer redator ver a sugestão — uma URL fabricada só';
        $lines[] = 'desperdiça essa chamada, nunca chega a aparecer pra ninguém.';
        $lines[] = '';
        $lines[] = '# ARTIGO';
        $lines[] = '- Título: ' . (string) ($article['title'] ?? '');
        $lines[] = '- Palavra-chave: ' . (string) ($article['focus_keyword'] ?? '');
        $lines[] = '- Meta descrição: ' . (string) ($article['meta_description'] ?? '');
        if ($existingUrls !== []) {
            $lines[] = '';
            $lines[] = '# FONTES JÁ USADAS (não repita nenhuma destas)';
            foreach ($existingUrls as $u) {
                $lines[] = '- ' . $u;
            }
        }
        $lines[] = '';
        $lines[] = '# REGRAS (mesmas de docs/ai/research.md)';
        $lines[] = '1. Nunca invente, chute ou reconstrua uma URL a partir do título/domínio/tema — só use uma';
        $lines[] = '   URL de uma fonte que você tem certeza que existe de verdade.';
        $lines[] = '2. Prefira a homepage ou uma página bem conhecida/estável de uma fonte confiável (órgão oficial,';
        $lines[] = '   instituição, veículo jornalístico sério, documentação oficial) a um caminho profundo chutado.';
        $lines[] = '3. Sem fonte nova o bastante e confiável: devolva menos itens (ou `suggestions: []`) — nunca';
        $lines[] = '   force pra completar uma quantidade.';
        $lines[] = '';
        $lines[] = 'Sugira até ' . self::REQUEST_COUNT . ' fontes novas e relevantes pro tema do artigo, no formato JSON pedido';
        $lines[] = '(url, title, publisher).';

        return implode("\n", $lines);
    }
}
