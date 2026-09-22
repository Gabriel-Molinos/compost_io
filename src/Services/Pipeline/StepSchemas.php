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

    /** @return array<string, mixed> */
    public static function seo(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'passes' => ['type' => 'boolean'],
                'issues' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'item'     => ['type' => 'string'],
                            'severity' => ['type' => 'string', 'enum' => ['block', 'warn']],
                            'fix'      => ['type' => 'string'],
                        ],
                        'required' => ['item', 'severity'],
                    ],
                ],
                'revised_meta_description' => ['type' => 'string'],
                'revised_slug'            => ['type' => 'string'],
                'cannibalization'         => ['type' => 'string', 'enum' => ['none', 'possible', 'high']],
            ],
            'required' => ['passes'],
        ];
    }

    /** @return array<string, mixed> */
    public static function compliance(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'approved'  => ['type' => 'boolean'],
                'blocking'  => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'rule'     => ['type' => 'string'],
                            'evidence' => ['type' => 'string'],
                            'fix'      => ['type' => 'string'],
                        ],
                        'required' => ['rule'],
                    ],
                ],
                'warnings' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'rule' => ['type' => 'string'],
                            'note' => ['type' => 'string'],
                        ],
                        'required' => ['rule'],
                    ],
                ],
            ],
            'required' => ['approved'],
        ];
    }

    /** @return array<string, mixed> */
    public static function image(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'style_notes' => ['type' => 'string'],
                'images'      => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'role'         => ['type' => 'string', 'enum' => ['FEATURED', 'BODY']],
                            'prompt'       => ['type' => 'string'],
                            'alt_text'     => ['type' => 'string'],
                            'aspect_ratio' => ['type' => 'string', 'enum' => ['1:1', '3:2', '2:3', '4:3', '3:4', '16:9', '9:16']],
                            'placement'    => ['type' => 'string'],
                        ],
                        'required' => ['role', 'prompt', 'alt_text', 'aspect_ratio'],
                    ],
                ],
                'notes' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['images'],
        ];
    }

    /** @return array<string, mixed> */
    public static function review(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'recommendation' => ['type' => 'string', 'enum' => ['ready_for_human', 'needs_fix', 'discard']],
                'summary'        => ['type' => 'string'],
                'strengths'      => ['type' => 'array', 'items' => ['type' => 'string']],
                'concerns'       => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'area' => ['type' => 'string'],
                            'note' => ['type' => 'string'],
                        ],
                        'required' => ['note'],
                    ],
                ],
                'assumptions_made' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['recommendation', 'summary'],
        ];
    }

    /** Não é um passo do pipeline de geração — usado por `BacklinkSuggestionService` (Central de Links). */
    public static function backlinkSuggestions(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'suggestions' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'article_id'  => ['type' => 'integer'],
                            'anchor_text' => ['type' => 'string'],
                            'reason'      => ['type' => 'string'],
                        ],
                        'required' => ['article_id', 'anchor_text', 'reason'],
                    ],
                ],
            ],
            'required' => ['suggestions'],
        ];
    }

    /** Não é um passo do pipeline de geração — usado por `ExternalLinkSuggestionService` (editor de corpo). */
    public static function externalLinkSuggestions(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'suggestions' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'url'       => ['type' => 'string'],
                            'title'     => ['type' => 'string'],
                            'publisher' => ['type' => 'string'],
                        ],
                        'required' => ['url', 'title', 'publisher'],
                    ],
                ],
            ],
            'required' => ['suggestions'],
        ];
    }

    /** Não é um passo do pipeline de geração — usado por `InternalLinkSuggestionService` (editor de corpo). */
    public static function internalLinkSuggestions(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'suggestions' => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'article_id' => ['type' => 'integer'],
                            'reason'     => ['type' => 'string'],
                        ],
                        'required' => ['article_id', 'reason'],
                    ],
                ],
            ],
            'required' => ['suggestions'],
        ];
    }

    /** Não é um passo do pipeline de geração — usado por `ResearchGapHintService` (página Fontes). */
    public static function researchGapHints(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'queries' => [
                    'type'        => 'array',
                    'description' => 'Buscas prontas pra colar num buscador (aspas, operadores site:/filetype: quando fizer sentido).',
                    'items'       => ['type' => 'string'],
                ],
                'keywords' => [
                    'type'        => 'array',
                    'description' => 'Termos/expressões-chave soltos, pra variar a busca além das queries prontas.',
                    'items'       => ['type' => 'string'],
                ],
                'source_types' => [
                    'type'        => 'array',
                    'description' => 'Tipos de fonte que provavelmente têm esse dado (ex.: "órgão estatístico oficial", "estudo acadêmico revisado por pares").',
                    'items'       => ['type' => 'string'],
                ],
            ],
            'required' => ['queries', 'keywords', 'source_types'],
        ];
    }
}
