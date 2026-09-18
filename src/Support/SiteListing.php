<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Regras da listagem de sites (/sites): estado do WordPress, tom do card, filtro e
 * ordenação. Puro (sem banco/HTTP) pra ser testável — a consulta é do
 * `SiteService::overview()`. Com ~60 sites o filtro roda em PHP sobre a lista já
 * carregada: uma consulta só, e as contagens dos chips saem da mesma lista.
 *
 * Linhas esperadas: as de `SiteService::overview()`.
 */
final class SiteListing
{
    public const STATUS = ['active', 'inactive', 'attention'];
    public const WP = ['ok', 'issue', 'none'];
    public const ORDER = ['name', 'attention', 'review', 'newest'];

    /** OK | FAILED | UNVERIFIED | NONE (sem credencial cadastrada). */
    public static function wpState(array $site): string
    {
        if (empty($site['wp_configured'])) {
            return 'NONE';
        }

        return match ($site['wp_status'] ?? null) {
            'OK'     => 'OK',
            'FAILED' => 'FAILED',
            default  => 'UNVERIFIED',
        };
    }

    /** Pede olho humano: artigo travado/com erro, ou conexão WordPress que falhou. */
    public static function needsAttention(array $site): bool
    {
        return (int) ($site['attention'] ?? 0) > 0 || self::wpState($site) === 'FAILED';
    }

    /**
     * Tom do card (mesmos nomes de `Labels::articleCardTone`): parado > problema >
     * rascunho esperando revisão > WordPress sem conexão válida > tudo em ordem.
     */
    public static function tone(array $site): string
    {
        if ((int) ($site['is_active'] ?? 0) !== 1) {
            return 'muted';
        }
        if (self::needsAttention($site)) {
            return 'danger';
        }
        if ((int) ($site['in_review'] ?? 0) > 0) {
            return 'cyan';
        }
        if (self::wpState($site) !== 'OK') {
            return 'warning';
        }

        return 'success';
    }

    /** Chave do idioma pro filtro: `pt`/`en`/`es` (preset) ou o texto salvo, minúsculo. */
    public static function languageKey(array $site): string
    {
        $language = trim((string) ($site['language'] ?? ''));

        return Languages::presetFor($language) ?? mb_strtolower($language);
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @return list<array<string, mixed>>
     */
    public static function filter(array $sites, string $q = '', string $status = '', string $wp = '', string $lang = ''): array
    {
        $q = mb_strtolower(trim($q));

        return array_values(array_filter($sites, static function (array $s) use ($q, $status, $wp, $lang): bool {
            if ($status === 'active' && (int) $s['is_active'] !== 1) {
                return false;
            }
            if ($status === 'inactive' && (int) $s['is_active'] === 1) {
                return false;
            }
            if ($status === 'attention' && !self::needsAttention($s)) {
                return false;
            }
            if ($wp !== '' && self::wpBucket($s) !== $wp) {
                return false;
            }
            if ($lang !== '' && self::languageKey($s) !== $lang) {
                return false;
            }
            if ($q !== '') {
                $hay = mb_strtolower(implode(' ', [$s['name'] ?? '', $s['niche'] ?? '', $s['wordpress_url'] ?? '', $s['language'] ?? '']));
                if (!str_contains($hay, $q)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /** ok | issue (falhou ou não verificada) | none (sem credencial). */
    public static function wpBucket(array $site): string
    {
        return match (self::wpState($site)) {
            'OK'   => 'ok',
            'NONE' => 'none',
            default => 'issue',
        };
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @return list<array<string, mixed>>
     */
    public static function sort(array $sites, string $order = 'name'): array
    {
        $byName = static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']);

        usort($sites, static fn (array $a, array $b): int => match ($order) {
            'attention' => ((int) self::needsAttention($b) <=> (int) self::needsAttention($a))
                ?: ((int) $b['attention'] <=> (int) $a['attention'])
                ?: ((int) $b['in_review'] <=> (int) $a['in_review'])
                ?: $byName($a, $b),
            'review'    => ((int) $b['in_review'] <=> (int) $a['in_review']) ?: $byName($a, $b),
            'newest'    => ((int) $b['id'] <=> (int) $a['id']),
            default     => $byName($a, $b),
        });

        return $sites;
    }
}
