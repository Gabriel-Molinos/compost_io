<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

/**
 * `responseSchema` (subset OpenAPI aceito pelo Gemini) de cada passo do pipeline.
 * Os campos aqui espelham o "Saída esperada (JSON)" dos arquivos `docs/ai/*.md`.
 */
final class StepSchemas
{
    /** @return array<string, mixed> */
    public static function planning(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'title'                => ['type' => 'string'],
                'focus_keyword'        => ['type' => 'string'],
                'category'             => ['type' => 'string'],
                'angle'                => ['type' => 'string'],
                'rationale'            => ['type' => 'string'],
                'cannibalization_risk' => ['type' => 'string', 'enum' => ['none', 'possible', 'high']],
                'cannibalization_note' => ['type' => 'string'],
            ],
            'required' => ['title', 'focus_keyword', 'category', 'angle', 'rationale', 'cannibalization_risk'],
        ];
    }

    /** @return array<string, mixed> */
    public static function research(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'findings' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'claim'        => ['type' => 'string'],
                            'detail'       => ['type' => 'string'],
                            'source_url'   => ['type' => 'string'],
                            'source_title' => ['type' => 'string'],
                            'publisher'    => ['type' => 'string'],
                            'accessed_at'  => ['type' => 'string'],
                            'confidence'   => ['type' => 'string', 'enum' => ['confirmed', 'partial', 'unverified']],
                        ],
                        'required' => ['claim', 'source_url'],
                    ],
                ],
                'gaps' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['findings'],
        ];
    }

    /** @return array<string, mixed> */
    public static function writing(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'title'                 => ['type' => 'string'],
                'slug'                  => ['type' => 'string'],
                'focus_keyword'         => ['type' => 'string'],
                'meta_description'      => ['type' => 'string'],
                'content_html'          => ['type' => 'string'],
                'word_count'            => ['type' => 'integer'],
                'internal_link_anchors' => ['type' => 'array', 'items' => ['type' => 'string']],
                'external_links'        => ['type' => 'array', 'items' => ['type' => 'string']],
                'open_questions'        => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['title', 'slug', 'meta_description', 'content_html'],
        ];
    }
}
