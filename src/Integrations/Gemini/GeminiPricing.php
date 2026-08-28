<?php

declare(strict_types=1);

namespace App\Integrations\Gemini;

use App\Integrations\AIResult;

/**
 * Estimativa de custo (USD) de uma chamada ao Gemini, para `ai_executions.cost`
 * (requisitos §95). Tokens de raciocínio (`thoughtsTokens`) contam como saída.
 *
 * Tarifas APROXIMADAS, das tabelas públicas do Google (https://ai.google.dev/pricing),
 * conferidas em 2026-08-28 — revisar quando o custo real dos primeiros artigos
 * estiver disponível (§95, regra de não-invenção §58).
 */
final class GeminiPricing
{
    /** [modelo => [input USD/1M, output USD/1M]] — prefixo casa por `str_starts_with`. */
    private const RATES = [
        'gemini-2.5-pro'   => [1.25, 10.00],
        'gemini-2.5-flash' => [0.30, 2.50],
        'gemini-2.0-flash' => [0.10, 0.40],
    ];

    private const FALLBACK = [1.25, 10.00];

    public static function estimate(AIResult $result): float
    {
        [$inRate, $outRate] = self::ratesFor($result->model);

        $inputCost = $result->promptTokens / 1_000_000 * $inRate;
        $outputCost = ($result->outputTokens + $result->thoughtsTokens) / 1_000_000 * $outRate;

        return round($inputCost + $outputCost, 6);
    }

    /** @return array{0: float, 1: float} */
    private static function ratesFor(string $model): array
    {
        foreach (self::RATES as $prefix => $rates) {
            if (str_starts_with($model, $prefix)) {
                return $rates;
            }
        }

        return self::FALLBACK;
    }
}
