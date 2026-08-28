<?php

declare(strict_types=1);

namespace App\Integrations;

/**
 * Resultado de uma chamada a um provedor de IA de texto. Imutável.
 *
 * `json` só é preenchido quando a chamada pediu saída estruturada
 * (`AIProvider::generateJson`) e o corpo retornado era JSON válido.
 */
final class AIResult
{
    /** @param array<string, mixed>|null $json */
    public function __construct(
        public readonly string $text,
        public readonly ?array $json,
        public readonly int $promptTokens,
        public readonly int $outputTokens,
        public readonly int $totalTokens,
        public readonly string $model,
        /** Tokens de "raciocínio" (modelos thinking, ex.: gemini-2.5-pro). Faturados como saída. */
        public readonly int $thoughtsTokens = 0,
    ) {
    }
}
