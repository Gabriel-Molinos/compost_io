<?php

declare(strict_types=1);

namespace App\Integrations\Image;

/**
 * Estimativa de custo (USD) por imagem gerada, para `ai_executions.cost`
 * (requisitos §95). Ao contrário do texto, a API de imagem cobra por imagem
 * (variando por resolução), não por token.
 *
 * Tarifas APROXIMADAS da documentação pública do Google
 * (https://ai.google.dev/gemini-api/docs/image-generation), conferidas em
 * 2026-08-28 — revisar com o custo real dos primeiros artigos (§95, regra §58).
 */
final class ImagePricing
{
    /** [prefixo do modelo => [1K => USD, 2K => USD, 4K => USD]]. */
    private const RATES = [
        'gemini-3-pro-image'     => ['1K' => 0.134, '2K' => 0.134, '4K' => 0.24],
        'gemini-3.1-flash-image' => ['1K' => 0.067, '2K' => 0.067, '4K' => 0.12],
        'gemini-3-flash-image'   => ['1K' => 0.067, '2K' => 0.067, '4K' => 0.12],
        'gemini-2.5-flash-image' => ['1K' => 0.039, '2K' => 0.039, '4K' => 0.039],
    ];

    private const FALLBACK = ['1K' => 0.134, '2K' => 0.134, '4K' => 0.24];

    public static function estimate(string $model, string $size): float
    {
        $table = self::FALLBACK;
        foreach (self::RATES as $prefix => $rates) {
            if (str_starts_with($model, $prefix)) {
                $table = $rates;
                break;
            }
        }

        return round($table[$size] ?? $table['2K'], 6);
    }
}
