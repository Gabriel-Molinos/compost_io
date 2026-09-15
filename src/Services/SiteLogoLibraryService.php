<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Biblioteca de logos prontas por domínio (`public/assets/site-logos-library/`,
 * achado real 2026-09-15: a empresa já tinha a logo de cada site salva num
 * arquivo nomeado pelo próprio domínio — ex. `gavsy.com.png` — em vez de um
 * banco genérico de ícones). Fica em `public/` (não em `storage/`, achado
 * real seguinte: o redator precisa VER e escolher visualmente no formulário
 * de cadastro, não só confiar num casamento automático silencioso).
 *
 * Casamento automático continua existindo (`findForDomain()`, usado quando
 * ninguém escolhe nada manualmente) — mas agora `all()` também alimenta um
 * seletor visual no formulário (`sites/form.php`), pro caso do domínio não
 * bater ou o redator preferir escolher outra.
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

        foreach ($this->listFiles() as $file) {
            if (self::filenameToDomain($file) === $host) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Todos os logos da biblioteca, pro seletor visual — nome pra exibir
     * (o domínio, sem sufixo de duplicado) + URL pública pra `<img>`.
     * `$excludeFilenames` tira as já ocupadas por outro site
     * (`SiteService::usedLogoLibraryFilenames()`, achado real 2026-09-15:
     * sem isso, dava pra escolher a mesma logo pra dois sites diferentes).
     *
     * @param list<string> $excludeFilenames
     * @return list<array{domain: string, filename: string, url: string}>
     */
    public function all(array $excludeFilenames = []): array
    {
        $excluded = array_flip($excludeFilenames);

        $out = [];
        foreach ($this->listFiles() as $file) {
            $filename = basename($file);
            if (isset($excluded[$filename])) {
                continue;
            }
            $out[] = [
                'domain'   => self::filenameToDomain($file),
                'filename' => $filename,
                'url'      => '/assets/' . self::DIR . '/' . rawurlencode($filename),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['domain'] <=> $b['domain']);

        return $out;
    }

    /**
     * Resolve um `filename` vindo do formulário (seleção manual no seletor
     * visual) pro caminho absoluto real — nunca confia direto no valor do
     * POST como caminho de arquivo (`basename()` + checagem contra a lista
     * real de arquivos, não concatenação direta, pra não abrir path
     * traversal). Fora da lista real → null, silenciosamente ignorado.
     */
    public function findByFilename(?string $filename): ?string
    {
        if ($filename === null || trim($filename) === '') {
            return null;
        }
        $safeName = basename(trim($filename));

        foreach ($this->listFiles() as $file) {
            if (basename($file) === $safeName) {
                return $file;
            }
        }

        return null;
    }

    /** @return list<string> caminhos absolutos */
    private function listFiles(): array
    {
        $dir = self::libraryDir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                $files[] = $file;
            }
        }

        return $files;
    }

    private static function libraryDir(): string
    {
        return dirname(__DIR__, 2) . '/public/assets/' . self::DIR;
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
