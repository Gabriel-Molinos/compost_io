<?php

declare(strict_types=1);

namespace App\Support;

final class Http
{
    public static function redirect(string $path, int $status = 302): never
    {
        header('Location: ' . $path, true, $status);
        exit;
    }

    /** Valor de campo do formulário enviado (POST), como string trimada. */
    public static function input(string $key, string $default = ''): string
    {
        $value = $_POST[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }
}
