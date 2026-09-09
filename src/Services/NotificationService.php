<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use PDO;

/**
 * Central de notificações (pedido do responsável, 2026-09-08): avisar sem
 * precisar que alguém esteja olhando a tela na hora — post publicado ou
 * falhou, admin te associou a um site, artigo que precisa de atenção
 * humana (BLOCKED/ERROR). Uma linha por destinatário (nunca "compartilhada"
 * entre vários usuários) — ver migration 0015 pro raciocínio completo.
 */
final class NotificationService
{
    public const TYPE_PUBLISH_SUCCESS = 'PUBLISH_SUCCESS';
    public const TYPE_PUBLISH_FAILED = 'PUBLISH_FAILED';
    public const TYPE_SITE_ASSIGNED = 'SITE_ASSIGNED';
    public const TYPE_ATTENTION = 'ATTENTION';
    public const TYPE_ARTICLE_READY = 'ARTICLE_READY';

    public function notify(int $userId, string $type, string $title, string $message, ?int $siteId = null, ?string $link = null): void
    {
        Connection::get()->prepare(
            'INSERT INTO notifications (user_id, type, title, message, site_id, link) VALUES (:u, :t, :ti, :m, :s, :l)'
        )->execute(['u' => $userId, 't' => $type, 'ti' => $title, 'm' => $message, 's' => $siteId, 'l' => $link]);
    }

    /**
     * Notifica todo mundo com acesso ao site — ADMINs ativos + Redator-Chefe
     * vinculado (o mesmo grupo que já enxerga o site hoje, AuthService::canAccessSite()).
     */
    public function notifySiteTeam(int $siteId, string $type, string $title, string $message, ?string $link = null): void
    {
        foreach ($this->recipientsForSite($siteId) as $userId) {
            $this->notify($userId, $type, $title, $message, $siteId, $link);
        }
    }

    public function unreadCount(int $userId): int
    {
        $stmt = Connection::get()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND read_at IS NULL');
        $stmt->execute(['u' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId, int $limit = 50): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT n.*, s.name AS site_name FROM notifications n
             LEFT JOIN sites s ON s.id = n.site_id
             WHERE n.user_id = :u
             ORDER BY n.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue('u', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id, int $userId): ?array
    {
        $stmt = Connection::get()->prepare('SELECT * FROM notifications WHERE id = :id AND user_id = :u LIMIT 1');
        $stmt->execute(['id' => $id, 'u' => $userId]);

        return $stmt->fetch() ?: null;
    }

    public function markRead(int $id, int $userId): void
    {
        Connection::get()->prepare(
            'UPDATE notifications SET read_at = NOW() WHERE id = :id AND user_id = :u AND read_at IS NULL'
        )->execute(['id' => $id, 'u' => $userId]);
    }

    public function markAllRead(int $userId): void
    {
        Connection::get()->prepare(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = :u AND read_at IS NULL'
        )->execute(['u' => $userId]);
    }

    /** @return list<int> */
    private function recipientsForSite(int $siteId): array
    {
        $stmt = Connection::get()->prepare(
            "SELECT id FROM users WHERE is_active = 1 AND role = 'ADMIN'
             UNION
             SELECT u.id FROM users u
             JOIN user_site us ON us.user_id = u.id
             WHERE u.is_active = 1 AND us.site_id = :s"
        );
        $stmt->execute(['s' => $siteId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
