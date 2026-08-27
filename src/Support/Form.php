<?php

declare(strict_types=1);

namespace App\Support;

use App\View;

/**
 * Campos de formulário consistentes (docs/technical/ui-ux-frontend.md §104):
 * label sempre visível e associada, erro em texto + aria-describedby/aria-invalid.
 */
final class Form
{
    private const INPUT = 'mt-1 w-full rounded-md border bg-surface-2 px-3 py-2 text-text-primary '
        . 'placeholder:text-text-muted focus:outline-none';

    /** @param array<string, string> $errors */
    public static function text(string $name, string $label, array $data, array $errors, string $type = 'text', bool $required = false): string
    {
        $id = 'f_' . $name;
        $value = View::e($data[$name] ?? '');
        $error = $errors[$name] ?? null;
        $border = $error !== null ? 'border-danger' : 'border-border focus:border-cyan';
        $req = $required ? ' required' : '';
        $aria = $error !== null ? " aria-invalid=\"true\" aria-describedby=\"{$id}_err\"" : '';

        return self::wrap($id, $label, $required, $error,
            "<input type=\"{$type}\" id=\"{$id}\" name=\"{$name}\" value=\"{$value}\"{$req}{$aria} class=\"" . self::INPUT . " {$border}\">"
        );
    }

    /** @param array<string, string> $errors */
    public static function textarea(string $name, string $label, array $data, array $errors, int $rows = 3): string
    {
        $id = 'f_' . $name;
        $value = View::e($data[$name] ?? '');
        $error = $errors[$name] ?? null;
        $border = $error !== null ? 'border-danger' : 'border-border focus:border-cyan';
        $aria = $error !== null ? " aria-invalid=\"true\" aria-describedby=\"{$id}_err\"" : '';

        return self::wrap($id, $label, false, $error,
            "<textarea id=\"{$id}\" name=\"{$name}\" rows=\"{$rows}\"{$aria} class=\"" . self::INPUT . " {$border}\">{$value}</textarea>"
        );
    }

    public static function checkbox(string $name, string $label, array $data, bool $default = false): string
    {
        $id = 'f_' . $name;
        $checked = (array_key_exists($name, $data) ? !empty($data[$name]) : $default) ? ' checked' : '';

        return '<label for="' . $id . '" class="flex items-center gap-2 text-sm text-text-secondary">'
            . "<input type=\"checkbox\" id=\"{$id}\" name=\"{$name}\" value=\"1\"{$checked} "
            . 'class="h-4 w-4 rounded border-border bg-surface-2 text-cyan focus:ring-cyan">'
            . View::e($label) . '</label>';
    }

    private static function wrap(string $id, string $label, bool $required, ?string $error, string $control): string
    {
        $mark = $required ? ' <span class="text-danger" aria-hidden="true">*</span>' : '';
        $err = $error !== null
            ? "<p id=\"{$id}_err\" class=\"mt-1 text-sm text-danger\">" . View::e($error) . '</p>'
            : '';

        return '<div>'
            . "<label for=\"{$id}\" class=\"block text-sm font-medium text-text-secondary\">" . View::e($label) . $mark . '</label>'
            . $control . $err . '</div>';
    }
}
