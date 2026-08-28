<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Gravação local das imagens geradas (integracoes.md §36.1):
 *
 *   public/assets/uploads/{site_id}/{article_id}/{nome}.{ext}
 *
 * Devolve o caminho web (`/assets/uploads/…`) que vai para `images.url`. O envio
 * para a media library do WordPress é a Fase 7 — a cópia local fica como origem.
 */
final class ImageStorage
{
    private string $publicDir;

    public function __construct(?string $publicDir = null)
    {
        $this->publicDir = rtrim($publicDir ?? dirname(__DIR__, 2) . '/public', '/');
    }

    /**
     * @return string caminho web relativo (ex.: /assets/uploads/2/7/featured-1.webp)
     * @throws RuntimeException
     */
    public function save(int $siteId, int $articleId, string $name, string $bytes, string $ext): string
    {
        $name = preg_replace('/[^a-z0-9_-]+/i', '-', $name) ?: 'image';
        $ext = preg_replace('/[^a-z0-9]+/i', '', $ext) ?: 'webp';

        $relDir = "/assets/uploads/{$siteId}/{$articleId}";
        $absDir = $this->publicDir . $relDir;

        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new RuntimeException("Não foi possível criar {$absDir}.");
        }

        $file = "{$name}.{$ext}";
        if (file_put_contents("{$absDir}/{$file}", $bytes) === false) {
            throw new RuntimeException("Falha ao gravar {$absDir}/{$file}.");
        }

        return "{$relDir}/{$file}";
    }

    /**
     * Apaga um arquivo gerado, a partir do caminho web gravado em `images.url`.
     * Só age dentro de `public/assets/uploads/` — ignora qualquer outra coisa.
     */
    public function delete(string $webPath): void
    {
        if (!str_starts_with($webPath, '/assets/uploads/') || str_contains($webPath, '..')) {
            return;
        }

        $abs = $this->publicDir . $webPath;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }
}
