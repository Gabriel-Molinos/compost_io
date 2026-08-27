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
}
