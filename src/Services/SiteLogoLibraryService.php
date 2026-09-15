<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Biblioteca de logos prontas por domínio (`storage/site-logos-library/`,
 * achado real 2026-09-15: a empresa já tinha a logo de cada site salva num
 * arquivo nomeado pelo próprio domínio — ex. `gavsy.com.png` — em vez de um
 * banco genérico de ícones). No cadastro/edição de um site, se ninguém
 * enviou logo manualmente e o domínio (`wordpress_url`) bate com um arquivo
 * daqui, usa esse arquivo automaticamente — sem precisar de tela de seleção
 * nem de marcar "ocupado": como o casamento é por domínio exato, uma logo só
 * pode valer pro site dono daquele domínio, então nunca colide com outro.
 */
final class SiteLogoLibraryService
{
    private const DIR = 'site-logos-library';

    /** Caminho absoluto do arquivo casado com o domínio, ou null se não achar. */
    public function findForDomain(?string $wordpressUrl): ?string
    {
        $host = self::normalizeDomain($wordpressUrl);
        if ($host === null) {
            return null;
        }

        $dir = self::libraryDir();
        if (!is_dir($dir)) {
            return null;
        }

        foreach (glob($dir . '/*') ?: [] as $file) {
            if (!is_file($file)) {
                continue;
            }
            if (self::filenameToDomain($file) === $host) {
                return $file;
            }
        }

        return null;
    }

    private static function libraryDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/' . self::DIR;
    }

    /**
     * Nome do arquivo sem extensão, sem o sufixo "(N)" de download duplicado
     * (ex.: "actiow.com (44).png" → "actiow.com"), tudo minúsculo.
     */
    private static function filenameToDomain(string $path): string
    {
        $base = pathinfo($path, PATHINFO_FILENAME);
        $base = preg_replace('/\s*\(\d+\)$/', '', $base) ?? $base;

        return strtolower(trim($base));
    }

    /** "https://www.Gavsy.com/" → "gavsy.com". Sem host válido → null. */
    private static function normalizeDomain(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (!str_contains($url, '://')) {
            $url = 'https://' . $url;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }
        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }
}
