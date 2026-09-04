<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\Session;

/**
 * Autenticação por sessão. Senha verificada com password_verify
 * (docs/technical/requisitos.md §64.2).
 */
final class AuthService
{
    private const SESSION_KEY = 'user_id';

    /** @var array<string, mixed>|null Cache do usuário no request atual. */
    private static ?array $current = null;

    /**
     * Valida email + senha. Retorna os dados do usuário (sem o hash) ou null.
     *
     * @return array<string, mixed>|null
     */
    public static function attempt(string $email, string $password): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, name, email, password_hash, role, is_active
             FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user === false || (int) $user['is_active'] !== 1) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);

        return $user;
    }

    /** @param array<string, mixed> $user */
    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set(self::SESSION_KEY, (int) $user['id']);
        self::$current = null;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$current = null;
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $id = Session::get(self::SESSION_KEY);
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return null;
        }

        $stmt = Connection::get()->prepare(
            'SELECT id, name, email, role, is_active, avatar_path FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => (int) $id]);
        $user = $stmt->fetch();

        if ($user === false || (int) $user['is_active'] !== 1) {
            return null;
        }

        return self::$current = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();

        return $user !== null && $user['role'] === 'ADMIN';
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user !== null ? (int) $user['id'] : null;
    }

    /** ADMIN acessa qualquer site; REDATOR_CHEFE só os vinculados (user_site). */
    public static function canAccessSite(int $siteId): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }
        if ($user['role'] === 'ADMIN') {
            return true;
        }

        return (new SiteService())->hasUser($siteId, (int) $user['id']);
    }
}
