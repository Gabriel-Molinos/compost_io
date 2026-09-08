<?php

declare(strict_types=1);

namespace App;

/**
 * Renderização de Views em PHP puro (ADR-008). A View gera $content, que é
 * embutido em um layout. Todo dado dinâmico deve passar por View::e().
 */
final class View
{
    private const VIEWS_DIR = __DIR__ . '/Views';

    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], string $layout = 'layout/base'): void
    {
        $viewFile = self::VIEWS_DIR . '/' . $template . '.php';
        $layoutFile = self::VIEWS_DIR . '/' . $layout . '.php';

        if (!is_file($viewFile)) {
            throw new \RuntimeException("View não encontrada: {$template}");
        }
        if (!is_file($layoutFile)) {
            throw new \RuntimeException("Layout não encontrado: {$layout}");
        }

        $title = $data['title'] ?? 'COMPOST';

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        require $layoutFile;
    }

    /** Escapa saída para HTML (docs/technical/ui-ux-frontend.md §103). */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * URL de um arquivo estático em `public/` com cache-busting (`?v=` = mtime
     * do arquivo) — sem isso, o navegador pode continuar servindo `app.css`
     * do cache depois de um redeploy/rebuild, mesmo com o arquivo mudado no
     * disco (a URL nunca muda de nome sozinha). Usado em `layout/base.php`/
     * `layout/auth.php` para `app.css` e `calendar.js`.
     */
    public static function asset(string $path): string
    {
        $file = dirname(__DIR__) . '/public/' . ltrim($path, '/');
        $mtime = is_file($file) ? filemtime($file) : false;

        return '/' . ltrim($path, '/') . ($mtime !== false ? '?v=' . $mtime : '');
    }

    /**
     * Data de hoje abreviada em pt-BR ("08 SET 2026"), pro rodapé de
     * layout/base.php e layout/auth.php. `date()` sozinho não localiza nome
     * de mês (depende de locale do SO, que não configuramos) — mesma
     * abordagem manual já usada em Views/sites/calendar/index.php.
     */
    public static function todayShort(): string
    {
        $meses = ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];

        return date('d') . ' ' . $meses[(int) date('n') - 1] . ' ' . date('Y');
    }
}
