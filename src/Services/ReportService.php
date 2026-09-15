<?php

declare(strict_types=1);

namespace App\Services;

use App\Cache\CacheService;
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

    private CacheService $cache;

    public function __construct(?CacheService $cache = null)
    {
        $this->cache = $cache ?? new CacheService();
    }

    /**
     * Só `goal_total` + `ai_cost` do mês — o que a Visão Geral do site
     * (`CostBudgetService`) realmente usa. 2 queries em vez das 10 de
     * `monthly()` (levantamento de performance, Fase 9): a Visão Geral é
     * carregada a cada visita, não só na aba Relatórios.
     *
     * Cacheado (achado real 2026-09-14, ver `docs/technical/cache.md`), TTL
     * curto (2 min) — é o gasto do mês corrente, ainda acumulando.
     *
     * @return array{goal_total: int|null, ai_cost: float}
     */
    public function currentSpend(int $siteId, string $period): array
    {
        return $this->cache->remember("report:current_spend:{$siteId}:{$period}", 120, function () use ($siteId, $period): array {
            $pdo = Connection::get();
            $win = $this->windowFor($siteId, $period);

            $stmt = $pdo->prepare('SELECT total_articles FROM goals WHERE site_id = :s AND period = :p LIMIT 1');
            $stmt->execute(['s' => $siteId, 'p' => $period]);
            $goalTotal = $stmt->fetchColumn();

            $costStmt = $pdo->prepare(
                "SELECT COALESCE(SUM(e.cost), 0) FROM ai_executions e
                 JOIN articles a ON a.id = e.article_id
                 WHERE a.site_id = :s AND e.created_at >= :a AND e.created_at < :b"
            );
            $costStmt->execute($win);

            return [
                'goal_total' => $goalTotal !== false ? (int) $goalTotal : null,
                'ai_cost'    => (float) $costStmt->fetchColumn(),
            ];
        });
    }

    /**
     * Cacheado (achado real 2026-09-14, ver `docs/technical/cache.md`): mês
     * atual ainda muda (TTL curto), mês passado é imutável (TTL longo) —
     * EXCETO `pending_now`, que por definição é um retrato de "agora" (ver
     * docblock da classe), não do período pedido — por isso fica de fora do
     * bloco cacheado e é sempre calculado fresco, mesmo quando o resto vem
     * do cache.
     *
     * @return array<string, mixed>
     */
    public function monthly(int $siteId, string $period): array
    {
        $isCurrentMonth = $period === (new DateTimeImmutable('now'))->format('Y-m');
        $ttl = $isCurrentMonth ? 120 : 86400;

        $report = $this->cache->remember(
            "report:monthly:{$siteId}:{$period}",
            $ttl,
            fn () => $this->computeMonthly($siteId, $period),
        );

        $report['pending_now'] = $this->count(Connection::get(),
            "SELECT COUNT(*) FROM articles
             WHERE site_id = :s AND deleted_at IS NULL AND status = 'IN_REVIEW'", ['s' => $siteId]);

        return $report;
    }

    /** @return array<string, mixed> sem `pending_now` — ver docblock de monthly() */
    private function computeMonthly(int $siteId, string $period): array
    {
        $pdo = Connection::get();
        $win = $this->windowFor($siteId, $period);

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
            'approval_rate'   => $decided > 0 ? round($approved / $decided * 100) : null,
            'avg_review_hours' => $avgReviewHours,
            'ai_cost'         => $cost,
            'reject_reasons'  => $reasons,
            'categories'      => $categories,
        ];
    }

    /**
     * Tendência dos últimos `$months` meses (incluindo `$period`), pra
     * visualização em gráfico na aba Relatórios (Fase 9) — a comparação de
     * `compare()` só olha 2 meses; isso dá uma visão mais longa. 3 queries
     * agregadas (`GROUP BY` por mês), não uma por mês — mesma preocupação de
     * performance da fatia "60+ sites" (§97), embora aqui seja só sob demanda.
     *
     * Cacheado (achado real 2026-09-14, ver `docs/technical/cache.md`), TTL
     * 5 min — 3 queries agrupadas, mais pesado que `currentSpend()`.
     *
     * @return array{periods: list<string>, produced: list<int>, published: list<int>, ai_cost: list<float>}
     */
    public function trend(int $siteId, string $period, int $months = 6): array
    {
        $months = max(2, min(24, $months));

        return $this->cache->remember("report:trend:{$siteId}:{$period}:{$months}", 300, function () use ($siteId, $period, $months): array {
            $pdo = Connection::get();

            $end = (new DateTimeImmutable($period . '-01'))->modify('+1 month');
            $start = $end->modify('-' . $months . ' months');

            $periods = [];
            for ($cursor = $start; $cursor < $end; $cursor = $cursor->modify('+1 month')) {
                $periods[] = $cursor->format('Y-m');
            }

            $win = ['s' => $siteId, 'a' => $start->format('Y-m-d H:i:s'), 'b' => $end->format('Y-m-d H:i:s')];

            $produced = $this->countByMonth($pdo,
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total
                 FROM articles
                 WHERE site_id = :s AND deleted_at IS NULL AND created_at >= :a AND created_at < :b
                 GROUP BY ym", $win);

            $published = $this->countByMonth($pdo,
                "SELECT DATE_FORMAT(sc.scheduled_date, '%Y-%m') AS ym, COUNT(*) AS total
                 FROM schedules sc JOIN articles a ON a.id = sc.article_id
                 WHERE a.site_id = :s AND sc.status = 'PUBLISHED'
                   AND sc.scheduled_date >= :a AND sc.scheduled_date < :b
                 GROUP BY ym", $win);

            $cost = $this->sumByMonth($pdo,
                "SELECT DATE_FORMAT(e.created_at, '%Y-%m') AS ym, SUM(e.cost) AS total
                 FROM ai_executions e JOIN articles a ON a.id = e.article_id
                 WHERE a.site_id = :s AND e.created_at >= :a AND e.created_at < :b
                 GROUP BY ym", $win);

            return [
                'periods'   => $periods,
                'produced'  => array_map(static fn (string $p): int => $produced[$p] ?? 0, $periods),
                'published' => array_map(static fn (string $p): int => $published[$p] ?? 0, $periods),
                'ai_cost'   => array_map(static fn (string $p): float => $cost[$p] ?? 0.0, $periods),
            ];
        });
    }

    /**
     * Comparação com o mês anterior (RF-013, fluxo-editorial §32) — "o que
     * melhorou / não melhorou". Só deltas dos números que `monthly()` já
     * calculou; nenhuma consulta nova.
     *
     * @param array<string, mixed> $current  saída de monthly() do mês exibido
     * @param array<string, mixed> $previous saída de monthly() do mês anterior
     * @return array<string, mixed>
     */
    public function compare(array $current, array $previous): array
    {
        $hadData = $previous['produced'] > 0
            || $previous['approved'] > 0
            || $previous['rejected'] > 0
            || $previous['ai_cost'] > 0;

        $delta = static function (mixed $now, mixed $before): ?array {
            if ($now === null || $before === null) {
                return null;
            }

            return ['value' => (float) $now - (float) $before, 'from' => (float) $before, 'to' => (float) $now];
        };

        $reasonMap = static function (array $rows): array {
            $map = [];
            foreach ($rows as $r) {
                $map[$r['reason']] = (int) $r['total'];
            }

            return $map;
        };
        $now = $reasonMap($current['reject_reasons']);
        $before = $reasonMap($previous['reject_reasons']);
        $reasons = [];
        foreach (array_keys($now + $before) as $key) {
            $reasons[] = [
                'reason' => $key,
                'from'   => $before[$key] ?? 0,
                'to'     => $now[$key] ?? 0,
                'delta'  => ($now[$key] ?? 0) - ($before[$key] ?? 0),
            ];
        }
        usort($reasons, static fn ($a, $b) => abs($b['delta']) <=> abs($a['delta']));

        return [
            'period'        => $previous['period'],
            'had_data'      => $hadData,
            'produced'      => $delta($current['produced'], $previous['produced']),
            'approved'      => $delta($current['approved'], $previous['approved']),
            'rejected'      => $delta($current['rejected'], $previous['rejected']),
            'published'     => $delta($current['published'], $previous['published']),
            'approval_rate' => $delta($current['approval_rate'], $previous['approval_rate']),
            'avg_review_hours' => $delta($current['avg_review_hours'], $previous['avg_review_hours']),
            'ai_cost'       => $delta($current['ai_cost'], $previous['ai_cost']),
            'reject_reasons' => $reasons,
        ];
    }

    /** @param array<string, mixed> $params */
    private function count(\PDO $pdo, string $sql, array $params): int
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $params @return array<string, int> "AAAA-MM" => total */
    private function countByMonth(\PDO $pdo, string $sql, array $params): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['ym']] = (int) $row['total'];
        }

        return $out;
    }

    /** @param array<string, mixed> $params @return array<string, float> "AAAA-MM" => soma */
    private function sumByMonth(\PDO $pdo, string $sql, array $params): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['ym']] = (float) $row['total'];
        }

        return $out;
    }

    /** Janela do mês (1º dia 00:00 até o 1º dia do mês seguinte) como params de query. */
    private function windowFor(int $siteId, string $period): array
    {
        $start = $period . '-01 00:00:00';
        $end = (new DateTimeImmutable($start))->modify('+1 month')->format('Y-m-d H:i:s');

        return ['s' => $siteId, 'a' => $start, 'b' => $end];
    }
}
