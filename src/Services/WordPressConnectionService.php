<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\Crypto;

/**
 * Conexão WordPress de um site (RF-016, docs/technical/seguranca.md §46-47).
 * A URL fica em `sites.wordpress_url`; usuário + Application Password cifrada
 * ficam em `site_wordpress_connections` (1 linha por site).
 */
final class WordPressConnectionService
{
    /**
     * Estado da conexão para exibição (sem a senha).
     *
     * @return array{url:?string, username:?string, status:string, last_verified_at:?string, configured:bool}
     */
    public function forSite(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT s.wordpress_url, c.username, c.status, c.last_verified_at
             FROM sites s
             LEFT JOIN site_wordpress_connections c ON c.site_id = s.id
             WHERE s.id = :s LIMIT 1'
        );
        $stmt->execute(['s' => $siteId]);
        $row = $stmt->fetch() ?: [];

        return [
            'url'              => $row['wordpress_url'] ?? null,
            'username'         => $row['username'] ?? null,
            'status'           => $row['status'] ?? 'UNVERIFIED',
            'last_verified_at' => $row['last_verified_at'] ?? null,
            'configured'       => !empty($row['username']),
        ];
    }

    public function hasCredential(int $siteId): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT 1 FROM site_wordpress_connections WHERE site_id = :s LIMIT 1'
        );
        $stmt->execute(['s' => $siteId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Salva URL + usuário e, se informada, a nova Application Password.
     * `$appPassword` nula/vazia mantém a credencial já gravada.
     */
    public function save(int $siteId, string $url, string $username, ?string $appPassword): void
    {
        $pdo = Connection::get();

        $pdo->prepare('UPDATE sites SET wordpress_url = :u WHERE id = :s')
            ->execute(['u' => $url !== '' ? $url : null, 's' => $siteId]);

        $newPassword = $appPassword !== null ? trim($appPassword) : '';

        if ($newPassword !== '') {
            // WordPress mostra a Application Password em grupos separados por espaço; são irrelevantes.
            $encrypted = Crypto::encrypt(str_replace(' ', '', $newPassword));
            $stmt = $pdo->prepare(
                'INSERT INTO site_wordpress_connections (site_id, username, app_password_encrypted, status)
                 VALUES (:s, :n, :p, :st)
                 ON DUPLICATE KEY UPDATE username = VALUES(username),
                                         app_password_encrypted = VALUES(app_password_encrypted),
                                         status = VALUES(status), last_verified_at = NULL'
            );
            $stmt->execute(['s' => $siteId, 'n' => $username, 'p' => $encrypted, 'st' => 'UNVERIFIED']);

            return;
        }

        // Sem senha nova: só atualiza o usuário se já existir uma credencial.
        $pdo->prepare('UPDATE site_wordpress_connections SET username = :n WHERE site_id = :s')
            ->execute(['n' => $username, 's' => $siteId]);
    }

    /** Application Password em texto claro (para uso imediato numa requisição). */
    public function appPassword(int $siteId): ?string
    {
        $stmt = Connection::get()->prepare(
            'SELECT app_password_encrypted FROM site_wordpress_connections WHERE site_id = :s LIMIT 1'
        );
        $stmt->execute(['s' => $siteId]);
        $blob = $stmt->fetchColumn();

        return $blob === false ? null : Crypto::decrypt((string) $blob);
    }

    public function markVerified(int $siteId, bool $ok): void
    {
        Connection::get()->prepare(
            'UPDATE site_wordpress_connections
             SET status = :st, last_verified_at = CASE WHEN :ok2 = 1 THEN CURRENT_TIMESTAMP ELSE last_verified_at END
             WHERE site_id = :s'
        )->execute(['st' => $ok ? 'OK' : 'FAILED', 'ok2' => $ok ? 1 : 0, 's' => $siteId]);
    }

    public function delete(int $siteId): void
    {
        Connection::get()->prepare('DELETE FROM site_wordpress_connections WHERE site_id = :s')
            ->execute(['s' => $siteId]);
    }
}
