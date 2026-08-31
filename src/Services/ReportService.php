<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use DateTimeImmutable;

/**
 * Relatório mensal do site (RF-013, fluxo-editorial §32). Só agregação de dados
 * já existentes — sem chamada a IA (isso é a fatia 8.3).
 *
 * Janela do mês: `created_at` para "produzidos" e custo, `reviewed_at` para
 * aprovados/rejeitados/tempo de revisão, `scheduled_date` para publicados.
 * "Pendentes" é um retrato do agora (artigos em revisão), não do mês.
 */
final class ReportService
{
    private const APPROVED_STATES = "('APPROVED','SCHEDULED','PUBLISHED')";

    /** @return array<string, mixed> */
    public function monthly(int $siteId, string $period): array
    {
        $pdo = Connection::get();
        $start = $period . '-01 00:00:00';
        $end = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d H:i:s');
        $win = ['s' => $siteId, 'a' => $start, 'b' => $end];

        $goal = null;
        $stmt = $pdo->prepare('SELECT id, total_articles, general_guidelines FROM goals WHERE site_id = :s AND period = :p LIMIT 1');
        $stmt->execute(['s' => $siteId, 'p' => $period]);
        $goalRow = $stmt->fetch();
        if ($goalRow !== false) {
            $goal = $goalRow;
        }

        $produced = $this->count($pdo,
            "SELECT COUNT(*) FROM articles
             WHERE site_id = :s AND deleted_at IS NULL AND created_at >= :a AND created_at < :b", $win);

        $approved = $this->count($pdo,
            "SELECT COUNT(*) FROM articles
             WHERE site_id = :s AND deleted_at IS NULL
               AND reviewed_at >= :a AND reviewed_at < :b
               AND status IN " . self::APPROVED_STATES, $win);

        $rejected = $this->count($pdo,
            "SELECT COUNT(*) FROM feedback f
             JOIN articles a ON a.id = f.article_id
             WHERE a.site_id = :s AND f.created_at >= :a AND f.created_at < :b", $win);

        $published = $this->count($pdo,
            "SELECT COUNT(*) FROM schedules sc
             JOIN articles a ON a.id = sc.article_id
             WHERE a.site_id = :s AND sc.status = 'PUBLISHED'
               AND sc.scheduled_date >= :a AND sc.scheduled_date < :b", $win);

        $pendingNow = $this->count($pdo,
            "SELECT COUNT(*) FROM articles
             WHERE site_id = :s AND deleted_at IS NULL AND status = 'IN_REVIEW'", ['s' => $siteId]);

        $avgReviewStmt = $pdo->prepare(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, review_started_at, reviewed_at))
             FROM articles
             WHERE site_id = :s AND deleted_at IS NULL
               AND review_started_at IS NOT NULL AND reviewed_at IS NOT NULL
               AND reviewed_at >= :a AND reviewed_at < :b"
        );
        $avgReviewStmt->execute($win);
        $avgMinutes = $avgReviewStmt->fetchColumn();
        $avgReviewHours = $avgMinutes !== null && $avgMinutes !== false ? round(((float) $avgMinutes) / 60, 1) : null;

        $costStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(e.cost), 0) FROM ai_executions e
             JOIN articles a ON a.id = e.article_id
             WHERE a.site_id = :s AND e.created_at >= :a AND e.created_at < :b"
        );
        $costStmt->execute($win);
        $cost = (float) $costStmt->fetchColumn();

        $reasonsStmt = $pdo->prepare(
            "SELECT f.reason, COUNT(*) AS total FROM feedback f
             JOIN articles a ON a.id = f.article_id
             WHERE a.site_id = :s AND f.created_at >= :a AND f.created_at < :b
             GROUP BY f.reason ORDER BY total DESC"
        );
        $reasonsStmt->execute($win);
        $reasons = $reasonsStmt->fetchAll();

        $catStmt = $pdo->prepare(
            "SELECT c.id, c.name,
                    COALESCE(gc.target_count, 0) AS target,
                    (SELECT COUNT(*) FROM articles a
                     WHERE a.category_id = c.id AND a.site_id = :s2 AND a.deleted_at IS NULL
                       AND a.reviewed_at >= :a AND a.reviewed_at < :b
                       AND a.status IN " . self::APPROVED_STATES . ") AS produced
             FROM categories c
             LEFT JOIN goal_categories gc ON gc.category_id = c.id AND gc.goal_id = :g
             WHERE c.site_id = :s
             ORDER BY c.name"
        );
        $catStmt->execute($win + [
            's2' => $siteId,
            'g'  => $goal !== null ? (int) $goal['id'] : 0,
        ]);
        $categories = $catStmt->fetchAll();

        $decided = $approved + $rejected;

        return [
            'period'          => $period,
            'goal_total'      => $goal !== null ? (int) $goal['total_articles'] : null,
            'goal_guidelines' => $goal['general_guidelines'] ?? null,
            'produced'        => $produced,
            'approved'        => $approved,
            'rejected'        => $rejected,
            'published'       => $published,
            'pending_now'     => $pendingNow,
            'approval_rate'   => $decided > 0 ? round($approved / $decided * 100) : null,
            'avg_review_hours' => $avgReviewHours,
            'ai_cost'         => $cost,
            'reject_reasons'  => $reasons,
            'categories'      => $categories,
        ];
    }

    /** @param array<string, mixed> $params */
    private function count(\PDO $pdo, string $sql, array $params): int
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
