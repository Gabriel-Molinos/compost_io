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
    public const TYPE_FEEDBACK = 'FEEDBACK';

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

    /**
     * Como {@see self::notifySiteTeam()}, mas no máximo 1 vez por dia por site/tipo/título —
     * pra avisos disparados por varredura periódica do worker (a cada 5 min), que senão
     * virariam spam enquanto a pendência não for resolvida. Devolve se enviou.
     */
    public function notifySiteTeamOncePerDay(int $siteId, string $type, string $title, string $message, ?string $link = null): bool
    {
        $stmt = Connection::get()->prepare(
            'SELECT 1 FROM notifications WHERE site_id = :s AND type = :t AND title = :ti AND DATE(created_at) = CURDATE() LIMIT 1'
        );
        $stmt->execute(['s' => $siteId, 't' => $type, 'ti' => $title]);
        if ($stmt->fetchColumn() !== false) {
            return false;
        }

        $this->notifySiteTeam($siteId, $type, $title, $message, $link);

        return true;
    }

    /**
     * Avisa um Redator-Chefe que ganhou acesso a um site — usado tanto quando o
     * admin vincula pela tela do usuário quanto pela tela do site (mesma
     * mensagem, um lugar só).
     */
    public function notifySiteAssigned(int $userId, int $siteId, string $siteName): void
    {
        $this->notify(
            $userId,
            self::TYPE_SITE_ASSIGNED,
            'Você foi vinculado a um site',
            'Um administrador te deu acesso a "' . $siteName . '".',
            $siteId,
            '/sites/' . $siteId,
        );
    }

    /** Avisa todo ADMIN ativo — usado pelo feedback geral sobre a plataforma (não é de um site específico). */
    public function notifyAdmins(string $type, string $title, string $message, ?string $link = null): void
    {
        $stmt = Connection::get()->query("SELECT id FROM users WHERE is_active = 1 AND role = 'ADMIN'");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
            $this->notify((int) $adminId, $type, $title, $message, null, $link);
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

    /** Volta uma notificação pra "não lida" (o botão de alternar da tela de notificações). */
    public function markUnread(int $id, int $userId): void
    {
        Connection::get()->prepare(
            'UPDATE notifications SET read_at = NULL WHERE id = :id AND user_id = :u AND read_at IS NOT NULL'
        )->execute(['id' => $id, 'u' => $userId]);
    }

    public function markAllRead(int $userId): void
    {
        Connection::get()->prepare(
            'UPDATE notifications SET read_at = NOW() WHERE user_id = :u AND read_at IS NULL'
        )->execute(['u' => $userId]);
    }

    /**
     * Id da notificação mais recente do usuário (0 se não tem nenhuma) —
     * ponto de partida do polling do sino (assets/js/notification-toast.js,
     * pedido 2026-09-22: pop-up + som quando chega uma notificação nova).
     * A PRIMEIRA chamada da página usa isto como baseline, sem listar nada
     * — notificação que já existia antes de abrir a página não deve virar
     * pop-up, só a que chegar depois.
     */
    public function latestId(int $userId): int
    {
        $stmt = Connection::get()->prepare('SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = :u');
        $stmt->execute(['u' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Notificações do usuário mais novas que `$afterId` (mais antigas
     * primeiro — a ordem em que os pop-ups devem aparecer). Usada pelo
     * polling do sino a cada rodada, depois da baseline de `latestId()`.
     *
     * @return list<array<string, mixed>>
     */
    public function newerThan(int $userId, int $afterId, int $limit = 20): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT id, type, title, message, link, created_at FROM notifications
             WHERE user_id = :u AND id > :after
             ORDER BY id ASC LIMIT :lim'
        );
        $stmt->bindValue('u', $userId, PDO::PARAM_INT);
        $stmt->bindValue('after', $afterId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
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
