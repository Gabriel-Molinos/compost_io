<?php

declare(strict_types=1);

namespace App\Integrations;

/**
 * Contrato de um provedor de IA de texto (integracoes.md §41). O resto da
 * aplicação depende desta interface, não de uma API específica — trocar
 * `GeminiProvider` por outro fornecedor não deve exigir mudança nos módulos
 * editoriais.
 */
interface AIProvider
{
    /**
     * Gera texto livre.
     *
     * @param string|null $systemInstruction instrução de sistema (ex.: Prompt Base)
     * @throws AIException
     */
    public function generateText(string $prompt, ?string $systemInstruction = null): AIResult;

    /**
     * Gera uma resposta que obedece a um schema JSON.
     *
     * @param array<string, mixed> $schema  JSON Schema (subset aceito pelo provedor)
     * @param string|null          $systemInstruction
     * @throws AIException
     */
    public function generateJson(string $prompt, array $schema, ?string $systemInstruction = null): AIResult;
}
