<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\Support\Session;

/**
 * Dados da sidebar (layout/base.php → layout/_dial.php), buscados de forma barata.
 *
 * A sidebar aparece em TODA página, e antes fazia 5 consultas em sequência
 * (notificações não lidas, feedback pendente, primeiro site, total de sites,
 * lista de atalhos). Com o banco gerenciado a ~280 ms por ida e volta, isso era
 * ~1,4 s de espera em cada navegação — o "meio travando tudo" que o responsável
 * sentiu na transição (2026-09-18). Agora:
 *   - `counts()`: as contagens saem numa consulta só (subselects);
 *   - `quickSites()`: a lista de atalhos fica na sessão por 60 s (é só um atalho
 *     de navegação; um site novo pode levar até 1 min pra aparecer ali).
 * O selo de notificações continua exato (vem de `counts()`, sem cache).
 */
final class SidebarService
{
    private const QUICK_SITES_TTL = 60;

    /**
     * @return array{unread: int, pendingFeedback: int, sitesTotal: int, firstSiteId: ?int}
     */
    public function counts(int $userId, bool $isAdmin): array
    {
        $stmt = Connection::get()->prepare(
            'SELECT
                (SELECT COUNT(*) FROM notifications WHERE user_id = :u1 AND read_at IS NULL) AS unread,
                (SELECT COUNT(*) FROM platform_feedback WHERE reviewed_at IS NULL) AS pending_feedback,
                (SELECT COUNT(*) FROM sites) AS sites_all,
                (SELECT COUNT(*) FROM user_site WHERE user_id = :u2) AS sites_user,
                (SELECT s.id FROM sites s JOIN user_site us ON us.site_id = s.id
                  WHERE us.user_id = :u3 ORDER BY s.name LIMIT 1) AS first_site'
        );
        $stmt->execute(['u1' => $userId, 'u2' => $userId, 'u3' => $userId]);
        $row = $stmt->fetch() ?: [];

        return [
            'unread'          => (int) ($row['unread'] ?? 0),
            'pendingFeedback' => $isAdmin ? (int) ($row['pending_feedback'] ?? 0) : 0,
            'sitesTotal'      => (int) ($isAdmin ? ($row['sites_all'] ?? 0) : ($row['sites_user'] ?? 0)),
            'firstSiteId'     => isset($row['first_site']) ? (int) $row['first_site'] : null,
        ];
    }

    /**
     * Atalhos de sites (com cap) — guardados na sessão por 60 s.
     *
     * @return list<array{id: int, name: string, is_active: int}>
     */
    public function quickSites(int $userId, bool $isAdmin): array
    {
        $cached = Session::get('sidebar_sites');
        if (is_array($cached) && ($cached['user'] ?? null) === $userId && (int) ($cached['at'] ?? 0) > time() - self::QUICK_SITES_TTL) {
            return $cached['sites'];
        }

        $sites = (new SiteService())->recentForSidebar($userId, $isAdmin);
        Session::set('sidebar_sites', ['user' => $userId, 'at' => time(), 'sites' => $sites]);

        return $sites;
    }
}
