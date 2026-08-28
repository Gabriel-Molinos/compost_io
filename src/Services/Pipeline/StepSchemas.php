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
}
