<?php

declare(strict_types=1);

namespace App\Support;

use App\Config\Env;

/**
 * Sessão nativa do PHP (docs/technical/requisitos.md §64.2) — sem biblioteca externa.
 * Cookie HttpOnly sempre; Secure fora de desenvolvimento.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'secure'   => Env::get('APP_ENV') !== 'development',
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        session_name('compost_session');
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /** Regenera o ID da sessão (contra fixation) preservando os dados. */
    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        session_destroy();
    }

    /** Mensagem de uso único (lida uma vez e removida). */
    public static function flash(string $key, string $message): void
    {
        self::set("_flash.{$key}", $message);
    }

    public static function pullFlash(string $key): ?string
    {
        $value = self::get("_flash.{$key}");
        self::forget("_flash.{$key}");

        return $value;
    }
}
