<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\AIException;
use App\Integrations\AIProvider;
use App\Integrations\Gemini\GeminiPricing;
use App\Integrations\Gemini\GeminiProvider;
use App\Integrations\WordPress\WordPressException;

/**
 * Sugestão automática de identidade editorial (pedido do responsável 2026-09-28):
 * na primeira conexão bem-sucedida com o WordPress, lê os posts publicados de
 * verdade e sugere nicho/público-alvo/tom/identidade editorial nas configurações
 * do site — sempre editável depois, nunca decide sozinho (`SiteService::update()`
 * continua sendo o único jeito de "confirmar").
 *
 * Duas guardas contra sugestão ruim:
 * - só roda se os 4 campos ainda estiverem vazios (`SiteService::suggestEditorialIdentity()`
 *   nunca pisa em cima do que um humano já escreveu, mesmo em corrida rara);
 * - site com poucos posts publicados (MIN_POSTS) não gera sugestão nenhuma — sites
 *   novos, ainda sem conteúdo, ficam com os campos em branco pra preencher à mão,
 *   como sempre (não tem base real pra "adivinhar" nada).
 *
 * Best-effort: chamado de dentro do auto-import da 1ª conexão
 * (`WordPressConnectionController::autoImportOnFirstConnection()`), que já é
 * best-effort — qualquer falha (WordPress fora do ar, IA fora do ar, resposta
 * fora do formato) é engolida e a conexão continua normalmente, só sem sugestão.
 */
final class EditorialIdentityAnalysisService
{
    /** Menos posts publicados que isto = site ainda "vazio" pra este propósito — não inventa identidade sem conteúdo real. */
    private const MIN_POSTS = 5;

    private const POSTS_SAMPLE = 12;

    private WordPressConnectionService $connections;
    private SiteService $sites;
    private AIProvider $ai;
    private SiteAiCostService $costs;

    /** @var (\Closure(int): list<array{title: string, excerpt: string}>) */
    private \Closure $fetchPosts;

    /**
     * @param (\Closure(int): list<array{title: string, excerpt: string}>)|null $fetchPosts
     *        ponto de substituição só pros testes — sem site WordPress de verdade pra apontar,
     *        não dá pra exercitar o caminho feliz completo (posts -> IA sugere -> aplica) sem isso.
     */
    public function __construct(
        ?WordPressConnectionService $connections = null,
        ?SiteService $sites = null,
        ?AIProvider $ai = null,
        ?\Closure $fetchPosts = null,
        ?SiteAiCostService $costs = null,
    ) {
        $this->connections = $connections ?? new WordPressConnectionService();
        $this->sites = $sites ?? new SiteService();
        $this->ai = $ai ?? new GeminiProvider();
        $this->fetchPosts = $fetchPosts ?? fn (int $siteId): array => $this->connections->client($siteId)->listRecentPostsForAnalysis(self::POSTS_SAMPLE);
        $this->costs = $costs ?? new SiteAiCostService();
    }

    /** @return bool true se uma sugestão foi gerada e aplicada */
    public function suggest(int $siteId): bool
    {
        $site = $this->sites->find($siteId);
        if ($site === null || $this->hasAnyIdentityFilled($site)) {
            return false;
        }

        try {
            $posts = ($this->fetchPosts)($siteId);
        } catch (WordPressException) {
            return false;
        }

        if (count($posts) < self::MIN_POSTS) {
            return false; // site novo/vazio (pedido do responsável) — sem posts o bastante, não sugere nada
        }

        try {
            $result = $this->ai->generateJson($this->prompt($posts), $this->schema(), $this->systemInstruction());
        } catch (AIException) {
            return false;
        }
        // Achado real 2026-09-28: o gasto acontece aqui, tenha a sugestão sido aplicada ou
        // não (ex.: corrida rara com edição manual entre a leitura e o UPDATE condicional
        // abaixo) — sem isto, esta chamada ficava fora do orçamento de IA da Visão Geral.
        $this->costs->log($siteId, SiteAiCostService::SOURCE_EDITORIAL_IDENTITY_SUGGESTION, GeminiPricing::estimate($result));

        $fields = $this->sanitize($result->json);

        return $fields !== null && $this->sites->suggestEditorialIdentity($siteId, $fields);
    }

    /** @param array<string, mixed> $site */
    private function hasAnyIdentityFilled(array $site): bool
    {
        foreach (['niche', 'target_audience', 'tone', 'editorial_identity'] as $key) {
            if (trim((string) ($site[$key] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /** @param list<array{title: string, excerpt: string}> $posts */
    private function prompt(array $posts): string
    {
        $lines = ['## Posts publicados recentemente neste site (mais recentes primeiro)'];
        foreach ($posts as $post) {
            $line = '- "' . $post['title'] . '"';
            if ($post['excerpt'] !== '') {
                $line .= ': ' . $post['excerpt'];
            }
            $lines[] = $line;
        }
        $lines[] = "\nCom base SÓ nesses posts reais, descreva a identidade editorial deste site no formato JSON pedido.";

        return implode("\n", $lines);
    }

    private function systemInstruction(): string
    {
        return <<<TXT
            Você é um estrategista editorial. Recebe títulos e resumos de posts
            REAIS já publicados num blog/site e deve inferir, em português do
            Brasil, a identidade editorial desse site.

            Regras:
            - Baseie-se SÓ no conteúdo fornecido — não invente segmento nem
              público que os posts não sustentem. Se o conteúdo for variado
              demais pra um nicho único, descreva o denominador comum.
            - "niche": até 5 palavras (ex.: "viagens econômicas", "finanças pessoais").
            - "target_audience": 1 frase curta e direta (ex.: "pessoas planejando a primeira viagem internacional com pouco orçamento").
            - "tone": 2 a 4 adjetivos separados por vírgula (ex.: "amigável, didático, direto").
            - "editorial_identity": 2 a 4 frases descrevendo o estilo editorial —
              o que o site cobre, como escreve, o que evita. Esse texto alimenta
              direto o prompt de geração de artigos, então seja concreto e útil,
              não genérico.
            TXT;
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        $str = ['type' => 'string'];

        return [
            'type'       => 'object',
            'properties' => [
                'niche'              => $str,
                'target_audience'    => $str,
                'tone'               => $str,
                'editorial_identity' => $str,
            ],
            'required' => ['niche', 'target_audience', 'tone', 'editorial_identity'],
        ];
    }

    /**
     * Limites iguais aos do formulário/coluna (`sites/form.php`) — a IA às vezes
     * ignora o pedido de tamanho no prompt, então corta aqui de verdade.
     *
     * @return array{niche: string, target_audience: string, tone: string, editorial_identity: string}|null
     */
    private function sanitize(mixed $json): ?array
    {
        if (!is_array($json)) {
            return null;
        }
        foreach (['niche', 'target_audience', 'tone', 'editorial_identity'] as $key) {
            if (!isset($json[$key]) || !is_string($json[$key]) || trim($json[$key]) === '') {
                return null;
            }
        }

        return [
            'niche'              => mb_substr(trim((string) $json['niche']), 0, 191),
            'target_audience'    => mb_substr(trim((string) $json['target_audience']), 0, 255),
            'tone'               => mb_substr(trim((string) $json['tone']), 0, 100),
            'editorial_identity' => mb_substr(trim((string) $json['editorial_identity']), 0, 5000),
        ];
    }
}
